<?php
// ── Konfigurasi Database ──────────────────────
// Sesuaikan dengan pengaturan XAMPP Anda
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // default XAMPP
define('DB_PASS', '');            // default XAMPP (kosong)
define('DB_NAME', 'webgis_poverty_mapping');
define('DB_CHARSET', 'utf8mb4');

// ── Upload Config ─────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// ── Session ───────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'error' => 'Koneksi DB gagal: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── JSON response helper ──────────────────────
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    header('Access-Control-Allow-Credentials: true');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Auth Helpers ──────────────────────────────

/**
 * Cek apakah user sudah login.
 * Return data user atau null.
 */
function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Paksa login. Jika belum login, return 401.
 */
function requireAuth(): array {
    $user = getCurrentUser();
    if (!$user) {
        jsonResponse(['success' => false, 'error' => 'Unauthorized — silakan login'], 401);
    }
    return $user;
}

/**
 * Paksa role tertentu. Jika role tidak sesuai, return 403.
 * @param array|string $roles Role yang diizinkan
 */
function requireRole($roles): array {
    $user = requireAuth();
    if (is_string($roles)) $roles = [$roles];
    if (!in_array($user['role'], $roles)) {
        jsonResponse(['success' => false, 'error' => 'Forbidden — Anda tidak memiliki akses'], 403);
    }
    return $user;
}

// ── Audit Log Helper ──────────────────────────

/**
 * Catat aktivitas ke audit_logs
 */
function auditLog(PDO $db, int $userId, string $aksi, ?string $targetType = null, ?int $targetId = null, $before = null, $after = null): void {
    $stmt = $db->prepare("
        INSERT INTO audit_logs (user_id, aksi, target_type, target_id, data_before, data_after, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $aksi,
        $targetType,
        $targetId,
        $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
        $after  ? json_encode($after, JSON_UNESCAPED_UNICODE)  : null,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
}