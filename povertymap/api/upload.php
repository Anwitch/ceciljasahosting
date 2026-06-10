<?php
require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method tidak didukung'], 405);
}

$user = requireRole(['admin', 'surveyor']);

// ── Validasi file ────────────────────────────
if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'error' => 'File foto wajib diunggah'], 422);
}

$file = $_FILES['foto'];

// Cek ukuran
if ($file['size'] > MAX_UPLOAD_SIZE) {
    jsonResponse(['success' => false, 'error' => 'Ukuran file melebihi batas 5MB'], 422);
}

// Cek tipe file
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    jsonResponse(['success' => false, 'error' => 'Hanya file JPEG dan PNG yang diizinkan'], 422);
}

// ── Buat folder uploads jika belum ada ───────
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// ── Generate nama file unik ──────────────────
$ext = ($mimeType === 'image/png') ? 'png' : 'jpg';
$filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath = UPLOAD_DIR . $filename;
$webPath  = 'uploads/' . $filename;

// ── Pindahkan file ───────────────────────────
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(['success' => false, 'error' => 'Gagal menyimpan file'], 500);
}

// ── Extract EXIF GPS ─────────────────────────
$lat = null;
$lng = null;
$hasGps = false;

if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
    $exif = @exif_read_data($destPath, 'GPS', true);
    if ($exif && isset($exif['GPS']['GPSLatitude']) && isset($exif['GPS']['GPSLongitude'])) {
        $lat = exifGpsToDecimal(
            $exif['GPS']['GPSLatitude'],
            $exif['GPS']['GPSLatitudeRef'] ?? 'N'
        );
        $lng = exifGpsToDecimal(
            $exif['GPS']['GPSLongitude'],
            $exif['GPS']['GPSLongitudeRef'] ?? 'E'
        );
        $hasGps = true;
    }
}

auditLog(getDB(), $user['id'], 'UPLOAD_FOTO', null, null, null, [
    'filename' => $filename, 'has_gps' => $hasGps
]);

jsonResponse([
    'success'   => true,
    'foto_path' => $webPath,
    'lat'       => $lat,
    'lng'       => $lng,
    'has_gps'   => $hasGps,
]);

// ══════════════════════════════════════════════
//  EXIF GPS Helper
// ══════════════════════════════════════════════

/**
 * Konversi EXIF GPS coordinate (DMS) ke desimal
 * @param array $coord Array of 3 fractions [degrees, minutes, seconds]
 * @param string $ref Hemisphere reference (N/S/E/W)
 */
function exifGpsToDecimal(array $coord, string $ref): float {
    $degrees = exifFractionToFloat($coord[0]);
    $minutes = exifFractionToFloat($coord[1]);
    $seconds = exifFractionToFloat($coord[2]);

    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

    if ($ref === 'S' || $ref === 'W') {
        $decimal *= -1;
    }

    return round($decimal, 8);
}

/**
 * Konversi fraction string "num/den" ke float
 */
function exifFractionToFloat(string $fraction): float {
    $parts = explode('/', $fraction);
    if (count($parts) === 2 && (float)$parts[1] !== 0.0) {
        return (float)$parts[0] / (float)$parts[1];
    }
    return (float)$parts[0];
}
