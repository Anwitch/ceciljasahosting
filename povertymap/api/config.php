<?php
// ── Konfigurasi Database ──────────────────────
// Sesuaikan dengan pengaturan XAMPP Anda
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'webgis_poverty_mapping');
define('DB_CHARSET', 'utf8mb4');

// ── Upload Config ─────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('JWT_SECRET', 'a_very_secure_random_key_for_webgis_poverty_mapping_2026');

// ── Session ───────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'error' => 'Koneksi DB gagal: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── JSON response helper ──────────────────────
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    header('Access-Control-Allow-Credentials: true');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Auth Helpers ──────────────────────────────

/**
 * JWT Helper functions for tab-session isolation
 */
function jwt_encode(array $payload, string $secret): string {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    
    $payloadJson = json_encode($payload);
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadJson));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function jwt_decode(string $jwt, string $secret): ?array {
    $tokenParts = explode('.', $jwt);
    if (count($tokenParts) !== 3) return null;
    
    $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0]));
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
    $signatureProvided = $tokenParts[2];
    
    // Check signature
    $base64UrlHeader = $tokenParts[0];
    $base64UrlPayload = $tokenParts[1];
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    if ($base64UrlSignature !== $signatureProvided) return null;
    
    return json_decode($payload, true);
}

function getAuthorizationHeader(): string {
    $headers = null;
    if (isset($_SERVER['HTTP_X_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_X_AUTHORIZATION"]);
    } else if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } else if (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_change_key_case($requestHeaders, CASE_LOWER);
        if (isset($requestHeaders['x-authorization'])) {
            $headers = trim($requestHeaders['x-authorization']);
        } else if (isset($requestHeaders['authorization'])) {
            $headers = trim($requestHeaders['authorization']);
        }
    }
    return $headers ?? '';
}

/**
 * Cek apakah user sudah login.
 * Return data user atau null.
 */
function getCurrentUser(): ?array {
    $authHeader = getAuthorizationHeader();
    if (preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
        $token = $matches[1];
        $payload = jwt_decode($token, JWT_SECRET);
        if ($payload) {
            return $payload;
        }
    }
    return null;
}

/**
 * Paksa login. Jika belum login, return 401.
 */
function requireAuth(): array {
    $user = getCurrentUser();
    if (!$user) {
        jsonResponse(['success' => false, 'error' => 'Unauthorized — silakan login'], 401);
    }
    return $user;
}

/**
 * Paksa role tertentu. Jika role tidak sesuai, return 403.
 * @param array|string $roles Role yang diizinkan
 */
function requireRole($roles): array {
    $user = requireAuth();
    if (is_string($roles)) $roles = [$roles];
    if (!in_array($user['role'], $roles)) {
        jsonResponse(['success' => false, 'error' => 'Forbidden — Anda tidak memiliki akses'], 403);
    }
    return $user;
}

// ── Audit Log Helper ──────────────────────────

/**
 * Catat aktivitas ke audit_logs
 */
function auditLog(PDO $db, int $userId, string $aksi, ?string $targetType = null, ?int $targetId = null, $before = null, $after = null): void {
    $stmt = $db->prepare("
        INSERT INTO audit_logs (user_id, aksi, target_type, target_id, data_before, data_after, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $aksi,
        $targetType,
        $targetId,
        $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
        $after  ? json_encode($after, JSON_UNESCAPED_UNICODE)  : null,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
}