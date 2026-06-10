<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method tidak didukung'], 405);
}

$admin = requireRole('admin');
$db    = getDB();

// Pagination
$page  = max(1, (int)($_GET['page']  ?? 1));
$limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

// Filter
$filterAksi = $_GET['aksi']    ?? null;
$filterUser = $_GET['user_id'] ?? null;

$where  = [];
$params = [];

if ($filterAksi) {
    $where[]  = "al.aksi LIKE ?";
    $params[] = "%$filterAksi%";
}
if ($filterUser) {
    $where[]  = "al.user_id = ?";
    $params[] = (int)$filterUser;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Total count
$countSql = "SELECT COUNT(*) FROM audit_logs al $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Data
$sql = "
    SELECT al.*, u.username, u.nama_lengkap
    FROM audit_logs al
    LEFT JOIN users u ON u.id = al.user_id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonResponse([
    'data'  => $rows,
    'page'  => $page,
    'limit' => $limit,
    'total' => $total,
    'pages' => ceil($total / $limit),
]);
