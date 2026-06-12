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

// ── GET ───────────────────────────────────────
if ($method === 'GET') {
    $user = getCurrentUser();

    // Filter tambahan: kecamatan
    $filterKec  = $_GET['kecamatan'] ?? null;

    $where   = [];
    $params  = [];

    if ($filterKec) { $where[] = "wm.kecamatan = ?"; $params[] = $filterKec; }

    if ($user && ($user['role'] === 'admin' || $user['role'] === 'surveyor' || $user['role'] === 'pemangku')) {
        // Admin, Surveyor, & Pemangku lihat semua data (untuk mencegah input ganda & analisis lengkap)
        $statusFilter = "";
    } else {
        // Publik: hanya verified
        $statusFilter = "AND wm.status = 'verified'";
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) . ' ' . $statusFilter : ($statusFilter ? 'WHERE 1=1 ' . $statusFilter : '');

    $sql = "
        SELECT wm.*,
               ri.nama  AS ibadah_nama,
               ri.jenis AS ibadah_jenis,
               u.nama_lengkap AS surveyor_nama
        FROM warga_miskin wm
        LEFT JOIN rumah_ibadah ri ON ri.id = wm.ibadah_id
        LEFT JOIN users u ON u.id = wm.surveyor_id
        $whereClause
        ORDER BY wm.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    jsonResponse(['data' => $rows]);
}

// ── POST: tambah warga ───────────────────────
if ($method === 'POST') {
    $user = requireRole(['admin', 'surveyor']);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    foreach (['nama_kk','lat','lng'] as $f) {
        if (empty($body[$f])) jsonResponse(['success'=>false,'error'=>"Field '$f' wajib diisi"], 422);
    }

    // Validasi NIK: harus 16 digit angka jika diisi
    $nik = $body['nik'] ?? null;
    if ($nik !== null && $nik !== '') {
        $nik = trim($nik);
        if (!preg_match('/^\d{16}$/', $nik)) {
            jsonResponse(['success'=>false,'error'=>'NIK harus tepat 16 digit angka'], 422);
        }
        // Cek duplikasi NIK
        $chk = $db->prepare("SELECT id FROM warga_miskin WHERE nik = ?");
        $chk->execute([$nik]);
        if ($chk->fetch()) {
            jsonResponse(['success'=>false,'error'=>'NIK Kepala Keluarga sudah terdaftar di sistem'], 422);
        }
    } else {
        $nik = null;
    }

    $lat = (float)$body['lat'];
    $lng = (float)$body['lng'];

    // Surveyor → pending, Admin → verified
    $status = ($user['role'] === 'admin') ? 'verified' : 'pending';

    // otomatis cari rumah ibadah terdekat (hanya jika verified)
    $ibadahId = ($status === 'verified') ? findNearestIbadah($db, $lat, $lng) : null;

    $bantuan = $body['bantuan_diterima'] ?? 'tidak_ada';
    if (!in_array($bantuan, ['tidak_ada', 'sembako', 'beasiswa', 'modal', 'tunai'])) {
        $bantuan = 'tidak_ada';
    }

    $stmt = $db->prepare("
        INSERT INTO warga_miskin (nama_kk, jumlah_anggota, keterangan, lat, lng, ibadah_id, foto_path, surveyor_id, status, alamat, nik, kecamatan, bantuan_diterima)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $body['nama_kk'],
        (int)($body['jumlah_anggota'] ?? 1),
        $body['keterangan'] ?? '',
        $lat, $lng, $ibadahId,
        $body['foto_path']  ?? null,
        $user['id'],
        $status,
        $body['alamat']     ?? null,
        $nik,
        $body['kecamatan']  ?? null,
        $bantuan,
    ]);

    $newId = (int)$db->lastInsertId();

    // ambil nama ibadah untuk respon
    $ibadahNama = null;
    if ($ibadahId) {
        $r = $db->prepare("SELECT nama FROM rumah_ibadah WHERE id = ?");
        $r->execute([$ibadahId]);
        $ibadahNama = $r->fetchColumn();
    }

    auditLog($db, $user['id'], 'CREATE_WARGA', 'warga_miskin', $newId, null, $body);

    jsonResponse([
        'success'     => true,
        'id'          => $newId,
        'status'      => $status,
        'ibadah_id'   => $ibadahId,
        'ibadah_nama' => $ibadahNama,
    ]);
}

// ── PUT: update warga ────────────────────────
if ($method === 'PUT') {
    $user = requireAuth();
    if (!$id) jsonResponse(['success'=>false,'error'=>'ID diperlukan'], 400);

    $old = $db->prepare("SELECT * FROM warga_miskin WHERE id = ?");
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
    if ($user['role'] === 'pemangku') {
        jsonResponse(['success'=>false,'error'=>'Anda tidak memiliki akses edit'], 403);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // Validasi NIK jika diubah
    if (array_key_exists('nik', $body) && $body['nik'] !== null && $body['nik'] !== '') {
        $nikVal = trim($body['nik']);
        if (!preg_match('/^\d{16}$/', $nikVal)) {
            jsonResponse(['success'=>false,'error'=>'NIK harus tepat 16 digit angka'], 422);
        }
        // Cek duplikasi NIK (kecuali data sendiri)
        $chk = $db->prepare("SELECT id FROM warga_miskin WHERE nik = ? AND id != ?");
        $chk->execute([$nikVal, $id]);
        if ($chk->fetch()) {
            jsonResponse(['success'=>false,'error'=>'NIK Kepala Keluarga sudah terdaftar di sistem'], 422);
        }
    }

    $fields = []; $vals = [];
    foreach (['nama_kk','jumlah_anggota','keterangan','lat','lng','foto_path','alamat','nik','kecamatan','bantuan_diterima'] as $f) {
        if (array_key_exists($f, $body)) {
            $fields[] = "$f = ?";
            if ($f === 'nik') {
                $nikVal = $body['nik'] !== null ? trim($body['nik']) : '';
                $vals[] = ($nikVal === '') ? null : $nikVal;
            } else {
                $vals[]   = in_array($f,['lat','lng']) ? (float)$body[$f]
                          : ($f==='jumlah_anggota' ? (int)$body[$f] : $body[$f]);
            }
        }
    }

    if ($user['role'] === 'surveyor') {
        $fields[] = "status = ?";
        $vals[] = 'pending';
        $fields[] = "alasan_penolakan = ?";
        $vals[] = null;
    }

    if (empty($fields)) jsonResponse(['success'=>false,'error'=>'Tidak ada data diubah'], 422);

    // jika koordinat berubah dan data verified, cari ulang ibadah terdekat
    if ($oldData['status'] === 'verified' && (array_key_exists('lat', $body) || array_key_exists('lng', $body))) {
        $lat = array_key_exists('lat',$body) ? (float)$body['lat'] : (float)$oldData['lat'];
        $lng = array_key_exists('lng',$body) ? (float)$body['lng'] : (float)$oldData['lng'];
        $ibadahId = findNearestIbadah($db, $lat, $lng);
        $fields[] = "ibadah_id = ?";
        $vals[]   = $ibadahId;
    }

    $vals[] = $id;
    $db->prepare("UPDATE warga_miskin SET ".implode(',',$fields)." WHERE id = ?")->execute($vals);

    auditLog($db, $user['id'], 'UPDATE_WARGA', 'warga_miskin', $id, $oldData, $body);

    jsonResponse(['success'=>true]);
}

// ── DELETE ───────────────────────────────────
if ($method === 'DELETE') {
    $user = requireAuth();
    if (!$id) jsonResponse(['success'=>false,'error'=>'ID diperlukan'], 400);

    $old = $db->prepare("SELECT * FROM warga_miskin WHERE id = ?");
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

    $db->prepare("DELETE FROM warga_miskin WHERE id = ?")->execute([$id]);

    auditLog($db, $user['id'], 'DELETE_WARGA', 'warga_miskin', $id, $oldData);

    jsonResponse(['success'=>true]);
}

jsonResponse(['error'=>'Method tidak didukung'], 405);
