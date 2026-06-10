<?php
// api/laporan.php - API CRUD untuk Laporan Kerusakan Jalan (Format GeoJSON Point)
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':    getLaporan($id);    break;
    case 'POST':   createLaporan();    break;
    case 'PUT':    updateLaporan($id); break;
    case 'DELETE': deleteLaporan($id); break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
}

// ─────────────────────────────────────────────
// Info anggaran berdasarkan status jalan
// ─────────────────────────────────────────────
function getInfoAnggaran($status_jalan) {
    switch ($status_jalan) {
        case 'Jalan Nasional':
            return ['pengelola' => 'Kementerian PUPR', 'sumber' => 'APBN'];
        case 'Jalan Provinsi':
            return ['pengelola' => 'Dinas PUPR Provinsi', 'sumber' => 'APBD Provinsi'];
        case 'Jalan Kabupaten':
            return ['pengelola' => 'Dinas PUPR Kabupaten/Kota', 'sumber' => 'APBD Kabupaten'];
        default:
            return ['pengelola' => 'Tidak Diketahui', 'sumber' => '-'];
    }
}

// ─────────────────────────────────────────────
// Helper: baris DB → array siap kirim ke client
// ─────────────────────────────────────────────
function rowToResponse($row) {
    $feature = json_decode($row['geojson'], true);
    $coords_geojson = $feature['geometry']['coordinates'] ?? [0, 0];

    return [
        'id'                => (int)$row['id'],
        'jalan_id'          => (int)$row['jalan_id'],
        'nama_jalan'        => $row['nama_jalan'],
        'status_jalan'      => $row['status_jalan'],
        'info_anggaran'     => getInfoAnggaran($row['status_jalan']),
        'nama_pelapor'      => $row['nama_pelapor'],
        'kategori_rusak'    => $row['kategori_rusak'],
        'tanggal_laporan'   => $row['tanggal_laporan'],
        'keterangan'        => $row['keterangan'],
        'foto_url'          => $row['foto_url'],
        'lat'               => $coords_geojson[1],
        'lng'               => $coords_geojson[0],
        'geojson'           => $feature,
        'created_at'        => $row['created_at'],
        'updated_at'        => $row['updated_at'],
    ];
}

function getLaporan($id = null) {
    $db = getDB();

    // Query parameter opsional: filter bulan, tahun, jalan_id
    $where = ['1=1'];
    $params = [];
    $types = '';

    if (isset($_GET['bulan_terakhir'])) {
        $n = (int)$_GET['bulan_terakhir'];
        $where[] = "tanggal_laporan >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)";
        $params[] = $n;
        $types .= 'i';
    }
    if (isset($_GET['tahun_terakhir'])) {
        $n = (int)$_GET['tahun_terakhir'];
        $where[] = "tanggal_laporan >= DATE_SUB(CURDATE(), INTERVAL ? YEAR)";
        $params[] = $n;
        $types .= 'i';
    }
    if (isset($_GET['jalan_id'])) {
        $where[] = "jalan_id = ?";
        $params[] = (int)$_GET['jalan_id'];
        $types .= 'i';
    }
    if (isset($_GET['kategori'])) {
        $where[] = "kategori_rusak = ?";
        $params[] = $_GET['kategori'];
        $types .= 's';
    }

    if ($id) {
        $stmt = $db->prepare("SELECT * FROM laporan_kerusakan WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) {
            echo json_encode(['success' => true, 'data' => rowToResponse($row)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        }
    } else {
        $sql = "SELECT * FROM laporan_kerusakan WHERE " . implode(' AND ', $where) . " ORDER BY tanggal_laporan DESC";
        $stmt = $db->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = rowToResponse($row);
        }

        // Hitung jalan yang sering rusak (muncul >= 3 laporan dalam 3 tahun)
        $res_sering = $db->query("
            SELECT jalan_id, nama_jalan, COUNT(*) as jumlah
            FROM laporan_kerusakan
            WHERE tanggal_laporan >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
            GROUP BY jalan_id, nama_jalan
            HAVING jumlah >= 3
            ORDER BY jumlah DESC
        ");
        $sering_rusak = [];
        while ($sr = $res_sering->fetch_assoc()) {
            $sering_rusak[] = ['jalan_id' => (int)$sr['jalan_id'], 'nama_jalan' => $sr['nama_jalan'], 'jumlah_laporan' => (int)$sr['jumlah']];
        }

        echo json_encode([
            'success'      => true,
            'data'         => $rows,
            'total'        => count($rows),
            'sering_rusak' => $sering_rusak
        ]);
    }
    $db->close();
}

function createLaporan() {
    $db = getDB();

    // Cek apakah ada upload foto
    $foto_url = null;
    if (!empty($_FILES['foto']['tmp_name'])) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $filename = 'laporan_' . time() . '_' . rand(1000,9999) . '.' . strtolower($ext);
        $upload_dir = '../uploads/laporan/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $filename)) {
            $foto_url = 'uploads/laporan/' . $filename;
        }
    }

    // Baca JSON dari body (jika bukan multipart)
    $input = [];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        // Foto bisa base64 embedded
        if (!empty($input['foto_base64'])) {
            $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $input['foto_base64']));
            $filename = 'laporan_' . time() . '_' . rand(1000,9999) . '.jpg';
            $upload_dir = '../uploads/laporan/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            file_put_contents($upload_dir . $filename, $imgData);
            $foto_url = 'uploads/laporan/' . $filename;
        }
    } else {
        $input = $_POST;
    }

    if (empty($input['lat']) || empty($input['lng']) || empty($input['kategori_rusak'])) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap (lat, lng, kategori_rusak wajib)']);
        return;
    }

    $lat           = (float)$input['lat'];
    $lng           = (float)$input['lng'];
    $jalan_id      = (int)($input['jalan_id'] ?? 0);
    $nama_jalan    = $db->real_escape_string($input['nama_jalan'] ?? 'Tidak Diketahui');
    $status_jalan  = $db->real_escape_string($input['status_jalan'] ?? '');
    $nama_pelapor  = $db->real_escape_string($input['nama_pelapor'] ?? 'Anonim');
    $kategori      = $db->real_escape_string($input['kategori_rusak']);
    $tanggal       = $db->real_escape_string($input['tanggal_laporan'] ?? date('Y-m-d'));
    $keterangan    = $db->real_escape_string($input['keterangan'] ?? '');

    // Build GeoJSON Point
    $geojson = json_encode([
        'type' => 'Feature',
        'properties' => [
            'nama_jalan'     => $nama_jalan,
            'status_jalan'   => $status_jalan,
            'kategori_rusak' => $kategori,
            'tanggal_laporan'=> $tanggal,
            'nama_pelapor'   => $nama_pelapor,
        ],
        'geometry' => [
            'type'        => 'Point',
            'coordinates' => [$lng, $lat], // GeoJSON: [lng, lat]
        ],
    ], JSON_UNESCAPED_UNICODE);

    $stmt = $db->prepare("INSERT INTO laporan_kerusakan (jalan_id, nama_jalan, status_jalan, nama_pelapor, kategori_rusak, tanggal_laporan, keterangan, foto_url, geojson) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issssssss', $jalan_id, $nama_jalan, $status_jalan, $nama_pelapor, $kategori, $tanggal, $keterangan, $foto_url, $geojson);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Laporan berhasil disimpan', 'id' => $db->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $db->error]);
    }
    $db->close();
}

function updateLaporan($id) {
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID diperlukan']); return; }
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);

    $kategori   = $db->real_escape_string($input['kategori_rusak'] ?? '');
    $tanggal    = $db->real_escape_string($input['tanggal_laporan'] ?? date('Y-m-d'));
    $keterangan = $db->real_escape_string($input['keterangan'] ?? '');

    $stmt = $db->prepare("UPDATE laporan_kerusakan SET kategori_rusak=?, tanggal_laporan=?, keterangan=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param('sssi', $kategori, $tanggal, $keterangan, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Laporan berhasil diperbarui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui: ' . $db->error]);
    }
    $db->close();
}

function deleteLaporan($id) {
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID diperlukan']); return; }
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM laporan_kerusakan WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Laporan berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $db->error]);
    }
    $db->close();
}
?>
