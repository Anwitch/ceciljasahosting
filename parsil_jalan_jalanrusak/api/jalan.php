<?php
// api/jalan.php - API CRUD untuk Data Jalan (Format GeoJSON)
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':    getJalan($id);    break;
    case 'POST':   createJalan();    break;
    case 'PUT':    updateJalan($id); break;
    case 'DELETE': deleteJalan($id); break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
}

// ─────────────────────────────────────────────
// Helper: baris DB → array siap kirim ke client
// Mengembalikan struktur flat yang dipakai frontend,
// dengan field `koordinat` berisi [[lat,lng],...] agar
// Leaflet langsung bisa dipakai, PLUS field `geojson`
// berisi GeoJSON Feature asli.
// ─────────────────────────────────────────────
function rowToResponse($row) {
    $feature = json_decode($row['geojson'], true);

    // GeoJSON coords = [lng, lat] → ubah ke [lat, lng] untuk Leaflet
    $coords_geojson = $feature['geometry']['coordinates'] ?? [];
    $koordinat_leaflet = array_map(fn($c) => [$c[1], $c[0]], $coords_geojson);

    return [
        'id'            => (int)$row['id'],
        'nama_jalan'    => $row['nama_jalan'],
        'status_jalan'  => $row['status_jalan'],
        'panjang_meter' => (float)$row['panjang_meter'],
        'koordinat'     => $koordinat_leaflet,   // [lat,lng] untuk Leaflet
        'geojson'       => $feature,             // GeoJSON Feature asli
        'created_at'    => $row['created_at'],
        'updated_at'    => $row['updated_at'],
    ];
}

// ─────────────────────────────────────────────
// Helper: koordinat [[lat,lng],...] → GeoJSON Feature string
// ─────────────────────────────────────────────
function buildGeoJSON($nama, $status, $panjang, $koordinat_leaflet) {
    // Leaflet: [lat, lng] → GeoJSON: [lng, lat]
    $coords = array_map(fn($c) => [(float)$c[1], (float)$c[0]], $koordinat_leaflet);

    $feature = [
        'type' => 'Feature',
        'properties' => [
            'nama_jalan'    => $nama,
            'status_jalan'  => $status,
            'panjang_meter' => (float)$panjang,
        ],
        'geometry' => [
            'type'        => 'LineString',
            'coordinates' => $coords,
        ],
    ];
    return json_encode($feature, JSON_UNESCAPED_UNICODE);
}

function getJalan($id = null) {
    $db = getDB();
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM jalan WHERE id = ?");
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
        $result = $db->query("SELECT * FROM jalan ORDER BY created_at DESC");
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = rowToResponse($row);
        }
        echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
    }
    $db->close();
}

function createJalan() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['nama_jalan']) || empty($input['status_jalan']) || empty($input['koordinat'])) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        return;
    }

    $nama    = $db->real_escape_string($input['nama_jalan']);
    $status  = $db->real_escape_string($input['status_jalan']);
    $panjang = (float)($input['panjang_meter'] ?? 0);
    $geojson = buildGeoJSON($nama, $status, $panjang, $input['koordinat']);

    $stmt = $db->prepare("INSERT INTO jalan (nama_jalan, status_jalan, panjang_meter, geojson) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssds', $nama, $status, $panjang, $geojson);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data jalan berhasil disimpan', 'id' => $db->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data: ' . $db->error]);
    }
    $db->close();
}

function updateJalan($id) {
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID diperlukan']); return; }
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);

    $nama    = $db->real_escape_string($input['nama_jalan']);
    $status  = $db->real_escape_string($input['status_jalan']);
    $panjang = (float)($input['panjang_meter'] ?? 0);
    $geojson = buildGeoJSON($nama, $status, $panjang, $input['koordinat']);

    $stmt = $db->prepare("UPDATE jalan SET nama_jalan=?, status_jalan=?, panjang_meter=?, geojson=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param('ssdsi', $nama, $status, $panjang, $geojson, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data jalan berhasil diperbarui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data: ' . $db->error]);
    }
    $db->close();
}

function deleteJalan($id) {
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID diperlukan']); return; }
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM jalan WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data jalan berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data']);
    }
    $db->close();
}
?>
