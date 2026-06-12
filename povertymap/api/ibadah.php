<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET: list semua / satu ───────────────────
if ($method === 'GET') {
    $user = getCurrentUser();

    if ($id) {
        $stmt = $db->prepare("SELECT * FROM rumah_ibadah WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        jsonResponse($row ?: ['error' => 'Tidak ditemukan'], $row ? 200 : 404);
    }

    // Admin, Surveyor, & Pemangku melihat semua, lainnya hanya verified
    if ($user && ($user['role'] === 'admin' || $user['role'] === 'surveyor' || $user['role'] === 'pemangku')) {
        $rows = $db->query("
            SELECT ri.*,
                   COUNT(wm.id)                       AS jumlah_kk,
                   COALESCE(SUM(wm.jumlah_anggota),0) AS jumlah_jiwa,
                   u.nama_lengkap                      AS surveyor_nama
            FROM rumah_ibadah ri
            LEFT JOIN warga_miskin wm ON wm.ibadah_id = ri.id AND wm.status = 'verified'
            LEFT JOIN users u ON u.id = ri.surveyor_id
            GROUP BY ri.id
            ORDER BY ri.created_at DESC
        ")->fetchAll();
    } else {
        // Pemangku / publik: hanya verified
        $rows = $db->query("
            SELECT ri.*,
                   COUNT(wm.id)                       AS jumlah_kk,
                   COALESCE(SUM(wm.jumlah_anggota),0) AS jumlah_jiwa
            FROM rumah_ibadah ri
            LEFT JOIN warga_miskin wm ON wm.ibadah_id = ri.id AND wm.status = 'verified'
            WHERE ri.status = 'verified'
            GROUP BY ri.id
            ORDER BY ri.created_at DESC
        ")->fetchAll();
    }

    jsonResponse(['data' => $rows]);
}

// ── POST: tambah ─────────────────────────────
if ($method === 'POST') {
    $user = requireRole(['admin', 'surveyor']);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $required = ['nama','jenis','pic','lat','lng'];
    foreach ($required as $f) {
        if (empty($body[$f])) jsonResponse(['success'=>false,'error'=>"Field '$f' wajib diisi"], 422);
    }

    // Surveyor → pending, Admin → verified
    $status = ($user['role'] === 'admin') ? 'verified' : 'pending';

    $stmt = $db->prepare("
        INSERT INTO rumah_ibadah (nama, jenis, alamat, pic, no_wa, radius, lat, lng, foto_path, surveyor_id, status, kecamatan)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $body['nama'],
        $body['jenis'],
        $body['alamat']     ?? '',
        $body['pic'],
        $body['no_wa']      ?? '',
        (int)($body['radius'] ?? 500),
        (float)$body['lat'],
        (float)$body['lng'],
        $body['foto_path']  ?? null,
        $user['id'],
        $status,
        $body['kecamatan']  ?? null,
    ]);
    $newId = (int)$db->lastInsertId();

    // re-assign warga hanya jika langsung verified
    if ($status === 'verified') {
        reassignAllWarga($db);
    }

    auditLog($db, $user['id'], 'CREATE_IBADAH', 'rumah_ibadah', $newId, null, $body);

    jsonResponse(['success'=>true, 'id'=>$newId, 'status'=>$status]);
}

// ── PUT: update ──────────────────────────────
if ($method === 'PUT') {
    $user = requireAuth();
    if (!$id) jsonResponse(['success'=>false,'error'=>'ID diperlukan'], 400);

    // Cek permission
    $old = $db->prepare("SELECT * FROM rumah_ibadah WHERE id = ?");
    $old->execute([$id]);
    $oldData = $old->fetch();
    if (!$oldData) jsonResponse(['success'=>false,'error'=>'Tidak ditemukan'], 404);

    // Surveyor hanya bisa edit milik sendiri yang masih pending atau rejected
    if ($user['role'] === 'surveyor') {
        if ((int)$oldData['surveyor_id'] !== $user['id']) {
            jsonResponse(['success'=>false,'error'=>'Anda hanya bisa edit data milik Anda'], 403);
        }
        if ($oldData['status'] !== 'pending' && $oldData['status'] !== 'rejected') {
            jsonResponse(['success'=>false,'error'=>'Data yang sudah diverifikasi tidak bisa diedit'], 403);
        }
    }
    // Pemangku tidak boleh edit
    if ($user['role'] === 'pemangku') {
        jsonResponse(['success'=>false,'error'=>'Anda tidak memiliki akses edit'], 403);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $fields = []; $vals = [];
    foreach (['nama','jenis','alamat','pic','no_wa','radius','lat','lng','foto_path','kecamatan'] as $f) {
        if (array_key_exists($f, $body)) {
            $fields[] = "$f = ?";
            $vals[]   = in_array($f,['lat','lng']) ? (float)$body[$f] : ($f==='radius' ? (int)$body[$f] : $body[$f]);
        }
    }

    if ($user['role'] === 'surveyor') {
        $fields[] = "status = ?";
        $vals[] = 'pending';
        $fields[] = "alasan_penolakan = ?";
        $vals[] = null;
    }

    if (empty($fields)) jsonResponse(['success'=>false,'error'=>'Tidak ada data diubah'], 422);
    $vals[] = $id;
    $db->prepare("UPDATE rumah_ibadah SET ".implode(',',$fields)." WHERE id = ?")->execute($vals);

    // re-assign ulang jika data verified
    if ($oldData['status'] === 'verified') {
        reassignAllWarga($db);
    }

    auditLog($db, $user['id'], 'UPDATE_IBADAH', 'rumah_ibadah', $id, $oldData, $body);

    jsonResponse(['success'=>true]);
}

// ── DELETE ───────────────────────────────────
if ($method === 'DELETE') {
    $user = requireAuth();
    if (!$id) jsonResponse(['success'=>false,'error'=>'ID diperlukan'], 400);

    $old = $db->prepare("SELECT * FROM rumah_ibadah WHERE id = ?");
    $old->execute([$id]);
    $oldData = $old->fetch();
    if (!$oldData) jsonResponse(['success'=>false,'error'=>'Tidak ditemukan'], 404);

    // Surveyor bisa hapus milik sendiri
    if ($user['role'] === 'surveyor') {
        if ((int)$oldData['surveyor_id'] !== $user['id']) {
            jsonResponse(['success'=>false,'error'=>'Anda hanya bisa hapus data milik Anda'], 403);
        }
    }
    if ($user['role'] === 'pemangku') {
        jsonResponse(['success'=>false,'error'=>'Anda tidak memiliki akses hapus'], 403);
    }

    // warga_miskin.ibadah_id akan SET NULL (FK ON DELETE SET NULL)
    $db->prepare("DELETE FROM rumah_ibadah WHERE id = ?")->execute([$id]);

    // Jika rumah ibadah terverifikasi dihapus, reassign warga sekitar
    if ($oldData['status'] === 'verified') {
        reassignAllWarga($db);
    }

    auditLog($db, $user['id'], 'DELETE_IBADAH', 'rumah_ibadah', $id, $oldData);

    jsonResponse(['success'=>true]);
}

jsonResponse(['error'=>'Method tidak didukung'], 405);
