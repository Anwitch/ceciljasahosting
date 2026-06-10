<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$type   = $_GET['type'] ?? '';   // 'ibadah' atau 'warga'
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!in_array($type, ['ibadah', 'warga'])) {
    jsonResponse(['error' => 'Parameter type harus "ibadah" atau "warga"'], 400);
}

$table = $type === 'ibadah' ? 'rumah_ibadah' : 'warga_miskin';

// ── GET: list data pending ───────────────────
if ($method === 'GET') {
    $admin = requireRole('admin');

    $status = $_GET['status'] ?? 'pending';
    if (!in_array($status, ['pending', 'verified', 'rejected'])) $status = 'pending';

    if ($type === 'ibadah') {
        $stmt = $db->prepare("
            SELECT ri.*, u.nama_lengkap AS surveyor_nama
            FROM rumah_ibadah ri
            LEFT JOIN users u ON u.id = ri.surveyor_id
            WHERE ri.status = ?
            ORDER BY ri.created_at DESC
        ");
    } else {
        $stmt = $db->prepare("
            SELECT wm.*, 
                   ri.nama AS ibadah_nama,
                   u.nama_lengkap AS surveyor_nama
            FROM warga_miskin wm
            LEFT JOIN rumah_ibadah ri ON ri.id = wm.ibadah_id
            LEFT JOIN users u ON u.id = wm.surveyor_id
            WHERE wm.status = ?
            ORDER BY wm.created_at DESC
        ");
    }
    $stmt->execute([$status]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

// ── PUT: approve / reject ────────────────────
if ($method === 'PUT') {
    $admin = requireRole('admin');
    if (!$id) jsonResponse(['success' => false, 'error' => 'ID diperlukan'], 400);

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $newStatus = $body['status'] ?? '';
    if (!in_array($newStatus, ['verified', 'rejected'])) {
        jsonResponse(['success' => false, 'error' => 'Status harus "verified" atau "rejected"'], 422);
    }

    // Ambil data lama
    $old = $db->prepare("SELECT * FROM $table WHERE id = ?");
    $old->execute([$id]);
    $oldData = $old->fetch();
    if (!$oldData) {
        jsonResponse(['success' => false, 'error' => 'Data tidak ditemukan'], 404);
    }

    // Update status
    $alasan = ($newStatus === 'rejected') ? ($body['alasan_penolakan'] ?? '') : null;
    $stmt = $db->prepare("UPDATE $table SET status = ?, alasan_penolakan = ? WHERE id = ?");
    $stmt->execute([$newStatus, $alasan, $id]);

    // Jika diverifikasi, proses spasial
    if ($newStatus === 'verified') {
        if ($type === 'warga') {
            // Cari ibadah terdekat untuk warga yang baru verified
            $ibadahId = findNearestIbadah($db, (float)$oldData['lat'], (float)$oldData['lng']);
            $db->prepare("UPDATE warga_miskin SET ibadah_id = ? WHERE id = ?")->execute([$ibadahId, $id]);
        } elseif ($type === 'ibadah') {
            // Ibadah baru verified → re-assign semua warga
            reassignAllWarga($db);
        }
    }

    $aksi = ($newStatus === 'verified') ? 'VERIFY_' : 'REJECT_';
    $aksi .= strtoupper($type);
    auditLog($db, $admin['id'], $aksi, $table, $id, $oldData, [
        'status' => $newStatus, 'alasan_penolakan' => $alasan
    ]);

    jsonResponse(['success' => true, 'status' => $newStatus]);
}

jsonResponse(['error' => 'Method tidak didukung'], 405);
