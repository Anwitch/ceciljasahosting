<?php
require_once __DIR__ . '/config.php';

// ── Haversine distance (meter) ──────────────
function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}

// ── Cari rumah ibadah terdekat yang menjangkau koordinat ──
// Hanya rumah ibadah yang sudah verified
function findNearestIbadah(PDO $db, float $lat, float $lng): ?int {
    $ibadahs = $db->query("SELECT id, lat, lng, radius FROM rumah_ibadah WHERE status = 'verified'")->fetchAll();
    $nearest = null;
    $minDist = PHP_FLOAT_MAX;
    foreach ($ibadahs as $ib) {
        $d = haversine($lat, $lng, (float)$ib['lat'], (float)$ib['lng']);
        if ($d <= $ib['radius'] && $d < $minDist) {
            $nearest = (int)$ib['id'];
            $minDist = $d;
        }
    }
    return $nearest;
}

// ── Re-assign SEMUA warga verified (dipanggil saat radius/ibadah berubah) ──
function reassignAllWarga(PDO $db): void {
    $wargas = $db->query("SELECT id, lat, lng FROM warga_miskin WHERE status = 'verified'")->fetchAll();
    $stmt   = $db->prepare("UPDATE warga_miskin SET ibadah_id = ? WHERE id = ?");
    foreach ($wargas as $w) {
        $ibadahId = findNearestIbadah($db, (float)$w['lat'], (float)$w['lng']);
        $stmt->execute([$ibadahId, $w['id']]);
    }
}
