<?php
// ============================================
// api.php — API Backend WebGIS SPBU
// ============================================

ini_set('display_errors', 0);
error_reporting(0);

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'PHP Error: ' . $err['message']]);
    }
});

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── GET ALL ───────────────────────────────────────────
if ($action === 'all') {
    $conn = getConnection();

    $check = $conn->query("SHOW TABLES LIKE 'spbu'");
    if ($check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => "Tabel 'spbu' tidak ditemukan. Pastikan Anda sudah import file database.sql"]);
        $conn->close(); exit;
    }

    $result = $conn->query(
        "SELECT id, nama, nomor_spbu, status, latitude, longitude,
                DATE_FORMAT(created_at, '%d %b %Y %H:%i') AS created_at
         FROM spbu ORDER BY created_at DESC"
    );

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
        $conn->close(); exit;
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    echo json_encode(['success' => true, 'data' => $rows]);
    $conn->close();

// ── SAVE ──────────────────────────────────────────────
} elseif ($action === 'save') {
    $nama       = trim($_POST['nama']       ?? '');
    $nomor_spbu = trim($_POST['nomor_spbu'] ?? '');
    $status     = trim($_POST['status']     ?? '');
    $latitude   = $_POST['latitude']        ?? '';
    $longitude  = $_POST['longitude']       ?? '';

    if (!$nama || !$nomor_spbu || !$status || $latitude === '' || $longitude === '') {
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi']); exit;
    }
    if (!in_array($status, ['buka 24 jam', 'tidak'])) {
        echo json_encode(['success' => false, 'message' => 'Status tidak valid']); exit;
    }

    $lat = (double) $latitude;
    $lng = (double) $longitude;

    $conn = getConnection();
    $stmt = $conn->prepare("INSERT INTO spbu (nama, nomor_spbu, status, latitude, longitude) VALUES (?,?,?,?,?)");
    if (!$stmt) { echo json_encode(['success' => false, 'message' => 'Prepare error: ' . $conn->error]); exit; }
    $stmt->bind_param('sssdd', $nama, $nomor_spbu, $status, $lat, $lng);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'SPBU berhasil disimpan', 'id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Execute error: ' . $conn->error]);
    }
    $stmt->close(); $conn->close();

// ── UPDATE ────────────────────────────────────────────
} elseif ($action === 'update') {
    $id         = (int) ($_POST['id']       ?? 0);
    $nama       = trim($_POST['nama']       ?? '');
    $nomor_spbu = trim($_POST['nomor_spbu'] ?? '');
    $status     = trim($_POST['status']     ?? '');
    $latitude   = $_POST['latitude']        ?? '';
    $longitude  = $_POST['longitude']       ?? '';

    if (!$id || !$nama || !$nomor_spbu || !$status || $latitude === '' || $longitude === '') {
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi']); exit;
    }

    $lat = (double) $latitude;
    $lng = (double) $longitude;

    $conn = getConnection();
    $stmt = $conn->prepare("UPDATE spbu SET nama=?, nomor_spbu=?, status=?, latitude=?, longitude=? WHERE id=?");
    if (!$stmt) { echo json_encode(['success' => false, 'message' => 'Prepare error: ' . $conn->error]); exit; }
    $stmt->bind_param('sssddi', $nama, $nomor_spbu, $status, $lat, $lng, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Execute error: ' . $conn->error]);
    }
    $stmt->close(); $conn->close();

// ── UPDATE KOORDINAT SAJA ─────────────────────────────
} elseif ($action === 'update_coords') {
    $id        = (int) ($_POST['id']   ?? 0);
    $latitude  = $_POST['latitude']    ?? '';
    $longitude = $_POST['longitude']   ?? '';

    if (!$id || $latitude === '' || $longitude === '') {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']); exit;
    }

    $lat = (double) $latitude;
    $lng = (double) $longitude;

    $conn = getConnection();
    $stmt = $conn->prepare("UPDATE spbu SET latitude=?, longitude=? WHERE id=?");
    if (!$stmt) { echo json_encode(['success' => false, 'message' => 'Prepare error: ' . $conn->error]); exit; }
    $stmt->bind_param('ddi', $lat, $lng, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Koordinat berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Execute error: ' . $conn->error]);
    }
    $stmt->close(); $conn->close();

// ── DELETE ────────────────────────────────────────────
} elseif ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID tidak valid']); exit; }

    $conn = getConnection();
    $stmt = $conn->prepare("DELETE FROM spbu WHERE id=?");
    if (!$stmt) { echo json_encode(['success' => false, 'message' => 'Prepare error: ' . $conn->error]); exit; }
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Execute error: ' . $conn->error]);
    }
    $stmt->close(); $conn->close();

// ── CEK KONEKSI (debug) ───────────────────────────────
} elseif ($action === 'ping') {
    $conn = getConnection();
    echo json_encode(['success' => true, 'message' => 'Koneksi OK', 'db' => DB_NAME]);
    $conn->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Action tidak dikenali: ' . $action]);
}
?>