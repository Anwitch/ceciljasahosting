<?php
// api/parsil.php - API CRUD untuk Data Parsil Tanah (Format GeoJSON)
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':    getParsil($id);    break;
    case 'POST':   createParsil();    break;
    case 'PUT':    updateParsil($id); break;
    case 'DELETE': deleteParsil($id); break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
}

// ─────────────────────────────────────────────
// Helper: baris DB → array siap kirim ke client
// ─────────────────────────────────────────────
function rowToResponse($row) {
    $feature = json_decode($row['geojson'], true);

    // GeoJSON Polygon coords = array of rings, ring[0] = outer ring
    // Setiap titik [lng, lat] → ubah ke [lat, lng] untuk Leaflet
    // Hilangkan titik penutup (sama dengan titik pertama) yang GeoJSON haruskan
    $ring_geojson = $feature['geometry']['coordinates'][0] ?? [];
    $koordinat_leaflet = array_map(fn($c) => [$c[1], $c[0]], $ring_geojson);
    // Hapus titik penutup jika sama dengan titik pertama
    $n = count($koordinat_leaflet);
    if ($n > 1 && $koordinat_leaflet[0] === $koordinat_leaflet[$n - 1]) {
        array_pop($koordinat_leaflet);
    }

    return [
        'id'                 => (int)$row['id'],
        'nama_parsil'        => $row['nomor_sertifikat'] ?? $row['nama_pemilik'] ?? 'Parsil',
        'nomor_sertifikat'   => $row['nomor_sertifikat'],
        'status_kepemilikan' => $row['status_kepemilikan'],
        'luas_meter2'        => (float)$row['luas_meter2'],
        'koordinat'          => $koordinat_leaflet,  // [lat,lng] untuk Leaflet
        'geojson'            => $feature,            // GeoJSON Feature asli
        'nama_pemilik'       => $row['nama_pemilik'],
        'created_at'         => $row['created_at'],
        'updated_at'         => $row['updated_at'],
    ];
}

// ─────────────────────────────────────────────
// Helper: koordinat [[lat,lng],...] → GeoJSON Feature string
// GeoJSON Polygon ring harus ditutup (titik pertama = terakhir)
// ─────────────────────────────────────────────
function buildGeoJSON($nosert, $status, $luas, $pemilik, $koordinat_leaflet) {
    // Leaflet: [lat, lng] → GeoJSON: [lng, lat]
    $coords = array_map(fn($c) => [(float)$c[1], (float)$c[0]], $koordinat_leaflet);

    // Tutup ring: tambahkan titik pertama di akhir jika belum
    if (!empty($coords) && $coords[0] !== $coords[count($coords) - 1]) {
        $coords[] = $coords[0];
    }

    $feature = [
        'type' => 'Feature',
        'properties' => [
            'nomor_sertifikat'   => $nosert,
            'status_kepemilikan' => $status,
            'luas_meter2'        => (float)$luas,
            'nama_pemilik'       => $pemilik,
        ],
        'geometry' => [
            'type'        => 'Polygon',
            'coordinates' => [$coords],   // array of rings
        ],
    ];
    return json_encode($feature, JSON_UNESCAPED_UNICODE);
}

function getParsil($id = null) {
    $db = getDB();
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM parsil_tanah WHERE id = ?");
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
        $result = $db->query("SELECT * FROM parsil_tanah ORDER BY created_at DESC");
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = rowToResponse($row);
        }
        echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
    }
    $db->close();
}

function createParsil() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['status_kepemilikan']) || empty($input['koordinat'])) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        return;
    }

    $nosert  = $db->real_escape_string($input['nomor_sertifikat'] ?? '');
    $status  = $db->real_escape_string($input['status_kepemilikan']);
    $luas    = (float)($input['luas_meter2'] ?? 0);
    $pemilik = $db->real_escape_string($input['nama_pemilik'] ?? '');
    $geojson = buildGeoJSON($nosert, $status, $luas, $pemilik, $input['koordinat']);

    $stmt = $db->prepare("INSERT INTO parsil_tanah (nomor_sertifikat, status_kepemilikan, luas_meter2, geojson, nama_pemilik) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssdss', $nosert, $status, $luas, $geojson, $pemilik);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data parsil berhasil disimpan', 'id' => $db->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data: ' . $db->error]);
    }
    $db->close();
}

function updateParsil($id) {
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID diperlukan']); return; }
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);

    $nosert  = $db->real_escape_string($input['nomor_sertifikat'] ?? '');
    $status  = $db->real_escape_string($input['status_kepemilikan']);
    $luas    = (float)($input['luas_meter2'] ?? 0);
    $pemilik = $db->real_escape_string($input['nama_pemilik'] ?? '');
    $geojson = buildGeoJSON($nosert, $status, $luas, $pemilik, $input['koordinat']);

    $stmt = $db->prepare("UPDATE parsil_tanah SET nomor_sertifikat=?, status_kepemilikan=?, luas_meter2=?, geojson=?, nama_pemilik=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param('ssdssi', $nosert, $status, $luas, $geojson, $pemilik, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data parsil berhasil diperbarui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data: ' . $db->error]);
    }
    $db->close();
}

function deleteParsil($id) {
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID diperlukan']); return; }
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM parsil_tanah WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data parsil berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data']);
    }
    $db->close();
}
?>
