<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

$db = getDB();
$user = getCurrentUser();

// Statistik utama — hanya data verified (digabung dalam 1 query)
$stats = $db->query("
    SELECT
        (SELECT COUNT(*) FROM rumah_ibadah WHERE status = 'verified') AS ibadah,
        COUNT(*) AS kk_total,
        COALESCE(SUM(CASE WHEN ibadah_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS kk_terlayani,
        COALESCE(SUM(jumlah_anggota), 0) AS jiwa_total,
        COALESCE(SUM(CASE WHEN ibadah_id IS NOT NULL THEN jumlah_anggota ELSE 0 END), 0) AS jiwa_terlayani,
        COALESCE(SUM(CASE WHEN bantuan_diterima = 'sembako' THEN 1 ELSE 0 END), 0) AS bantuan_sembako,
        COALESCE(SUM(CASE WHEN bantuan_diterima = 'beasiswa' THEN 1 ELSE 0 END), 0) AS bantuan_beasiswa,
        COALESCE(SUM(CASE WHEN bantuan_diterima = 'modal' THEN 1 ELSE 0 END), 0) AS bantuan_modal,
        COALESCE(SUM(CASE WHEN bantuan_diterima = 'tunai' THEN 1 ELSE 0 END), 0) AS bantuan_tunai,
        COALESCE(SUM(CASE WHEN bantuan_diterima = 'tidak_ada' THEN 1 ELSE 0 END), 0) AS bantuan_tidak_ada
    FROM warga_miskin
    WHERE status = 'verified'
")->fetch(PDO::FETCH_ASSOC);

$totalIbadah  = (int)($stats['ibadah'] ?? 0);
$totalWarga   = (int)($stats['kk_total'] ?? 0);
$totalCovered = (int)($stats['kk_terlayani'] ?? 0);
$totalJiwa    = (int)($stats['jiwa_total'] ?? 0);
$jiwaLayan    = (int)($stats['jiwa_terlayani'] ?? 0);

$response = [
    'ibadah'            => $totalIbadah,
    'kk_total'          => $totalWarga,
    'kk_terlayani'      => $totalCovered,
    'kk_belum'          => $totalWarga - $totalCovered,
    'jiwa_total'        => $totalJiwa,
    'jiwa_terlayani'    => $jiwaLayan,
    'bantuan_sembako'   => (int)($stats['bantuan_sembako'] ?? 0),
    'bantuan_beasiswa'  => (int)($stats['bantuan_beasiswa'] ?? 0),
    'bantuan_modal'     => (int)($stats['bantuan_modal'] ?? 0),
    'bantuan_tunai'     => (int)($stats['bantuan_tunai'] ?? 0),
    'bantuan_tidak_ada' => (int)($stats['bantuan_tidak_ada'] ?? 0),
];

// Data pending — hanya untuk admin (digabung dalam 1 query)
if ($user && $user['role'] === 'admin') {
    $adminStats = $db->query("
        SELECT
            (SELECT COUNT(*) FROM rumah_ibadah WHERE status = 'pending') AS pending_ibadah,
            (SELECT COUNT(*) FROM warga_miskin WHERE status = 'pending') AS pending_warga,
            (SELECT COUNT(*) FROM users WHERE is_active = 1) AS total_users
    ")->fetch(PDO::FETCH_ASSOC);
    $response['pending_ibadah'] = (int)($adminStats['pending_ibadah'] ?? 0);
    $response['pending_warga']  = (int)($adminStats['pending_warga'] ?? 0);
    $response['total_users']    = (int)($adminStats['total_users'] ?? 0);
}

// Data pending milik surveyor (digabung dalam 1 query)
if ($user && $user['role'] === 'surveyor') {
    $stmt = $db->prepare("
        SELECT
            (SELECT COUNT(*) FROM warga_miskin WHERE surveyor_id = ? AND status = 'pending') AS my_pending_warga,
            (SELECT COUNT(*) FROM rumah_ibadah WHERE surveyor_id = ? AND status = 'pending') AS my_pending_ibadah
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $surveyorStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['my_pending_warga'] = (int)($surveyorStats['my_pending_warga'] ?? 0);
    $response['my_pending_ibadah'] = (int)($surveyorStats['my_pending_ibadah'] ?? 0);
}

jsonResponse($response);