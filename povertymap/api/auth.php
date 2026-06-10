<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$db     = getDB();
$action = $_GET['action'] ?? '';

// ══════════════════════════════════════════════
//  LOGIN
// ══════════════════════════════════════════════
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if (!$username || !$password) {
        jsonResponse(['success' => false, 'error' => 'Username dan password wajib diisi'], 422);
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(['success' => false, 'error' => 'Username atau password salah'], 401);
    }

    // Set session
    $_SESSION['user'] = [
        'id'           => (int)$user['id'],
        'username'     => $user['username'],
        'nama_lengkap' => $user['nama_lengkap'],
        'role'         => $user['role'],
    ];

    auditLog($db, (int)$user['id'], 'LOGIN', 'users', (int)$user['id']);

    jsonResponse([
        'success' => true,
        'user'    => $_SESSION['user']
    ]);
}

// ══════════════════════════════════════════════
//  LOGOUT
// ══════════════════════════════════════════════
if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = getCurrentUser();
    if ($user) {
        auditLog($db, $user['id'], 'LOGOUT', 'users', $user['id']);
    }
    session_destroy();
    jsonResponse(['success' => true]);
}

// ══════════════════════════════════════════════
//  ME — cek session aktif
// ══════════════════════════════════════════════
if ($action === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = getCurrentUser();
    if (!$user) {
        jsonResponse(['success' => false, 'logged_in' => false], 200);
    }
    jsonResponse(['success' => true, 'logged_in' => true, 'user' => $user]);
}

// ══════════════════════════════════════════════
//  REGISTER — admin only
// ══════════════════════════════════════════════
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = requireRole('admin');
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];

    $username     = trim($body['username'] ?? '');
    $password     = $body['password'] ?? '';
    $nama_lengkap = trim($body['nama_lengkap'] ?? '');
    $role         = $body['role'] ?? 'surveyor';

    if (!$username || !$password || !$nama_lengkap) {
        jsonResponse(['success' => false, 'error' => 'Semua field wajib diisi'], 422);
    }
    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'error' => 'Password minimal 6 karakter'], 422);
    }
    if (!in_array($role, ['admin', 'surveyor', 'pemangku'])) {
        jsonResponse(['success' => false, 'error' => 'Role tidak valid'], 422);
    }

    // Cek username unik
    $check = $db->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'error' => 'Username sudah digunakan'], 409);
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $hashed, $nama_lengkap, $role]);
    $newId = (int)$db->lastInsertId();

    auditLog($db, $admin['id'], 'CREATE_USER', 'users', $newId, null, [
        'username' => $username, 'nama_lengkap' => $nama_lengkap, 'role' => $role
    ]);

    jsonResponse(['success' => true, 'id' => $newId]);
}

// ══════════════════════════════════════════════
//  LIST USERS — admin only
// ══════════════════════════════════════════════
if ($action === 'users' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireRole('admin');
    $rows = $db->query("SELECT id, username, nama_lengkap, role, is_active, created_at FROM users ORDER BY created_at DESC")->fetchAll();
    jsonResponse(['data' => $rows]);
}

// ══════════════════════════════════════════════
//  UPDATE USER — admin only
// ══════════════════════════════════════════════
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $admin = requireRole('admin');
    $id    = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$id) jsonResponse(['success' => false, 'error' => 'ID diperlukan'], 400);

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // Ambil data lama
    $old = $db->prepare("SELECT id, username, nama_lengkap, role, is_active FROM users WHERE id = ?");
    $old->execute([$id]);
    $oldData = $old->fetch();
    if (!$oldData) jsonResponse(['success' => false, 'error' => 'User tidak ditemukan'], 404);

    $fields = []; $vals = [];

    if (!empty($body['nama_lengkap'])) {
        $fields[] = "nama_lengkap = ?"; $vals[] = trim($body['nama_lengkap']);
    }
    if (!empty($body['role']) && in_array($body['role'], ['admin','surveyor','pemangku'])) {
        $fields[] = "role = ?"; $vals[] = $body['role'];
    }
    if (isset($body['is_active'])) {
        $fields[] = "is_active = ?"; $vals[] = $body['is_active'] ? 1 : 0;
    }
    if (!empty($body['password']) && strlen($body['password']) >= 6) {
        $fields[] = "password = ?"; $vals[] = password_hash($body['password'], PASSWORD_BCRYPT);
    }

    if (empty($fields)) jsonResponse(['success' => false, 'error' => 'Tidak ada data diubah'], 422);

    $vals[] = $id;
    $db->prepare("UPDATE users SET " . implode(',', $fields) . " WHERE id = ?")->execute($vals);

    auditLog($db, $admin['id'], 'UPDATE_USER', 'users', $id, $oldData, $body);

    jsonResponse(['success' => true]);
}

// ══════════════════════════════════════════════
//  DELETE (deactivate) USER — admin only
// ══════════════════════════════════════════════
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $admin = requireRole('admin');
    $id    = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$id) jsonResponse(['success' => false, 'error' => 'ID diperlukan'], 400);

    if ($id === $admin['id']) {
        jsonResponse(['success' => false, 'error' => 'Tidak bisa menghapus akun sendiri'], 400);
    }

    $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$id]);

    auditLog($db, $admin['id'], 'DEACTIVATE_USER', 'users', $id);

    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Action tidak valid'], 400);
