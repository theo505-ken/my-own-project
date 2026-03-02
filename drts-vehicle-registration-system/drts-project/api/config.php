<?php
// ============================================================
// DRTS VRS - Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'drts_vrs');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'DRTS Vehicle Registration System');
define('APP_URL', 'http://localhost');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB

// Stripe keys (replace with real keys)
define('STRIPE_PUBLIC_KEY', 'pk_test_YOUR_STRIPE_PUBLIC_KEY');
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_STRIPE_SECRET_KEY');

define('SESSION_LIFETIME', 3600); // 1 hour
define('JWT_SECRET', 'drts_jwt_secret_change_in_production_2026');

// Allowed MIME types for file uploads
define('ALLOWED_MIME_TYPES', [
    'image/jpeg', 'image/png', 'image/webp',
    'application/pdf'
]);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================================
// Database connection (PDO singleton)
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

// ============================================================
// JSON response helper
// ============================================================
function respond(array $data, int $code = 200): void {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ============================================================
// Auth helpers
// ============================================================
function requireAuth(array $roles = []): array {
    $headers = getallheaders();
    $token   = $headers['Authorization'] ?? '';
    $token   = str_replace('Bearer ', '', $token);

    if (empty($token)) {
        respond(['error' => 'Unauthorized'], 401);
    }

    // Validate session token against DB
    $db   = getDB();
    $stmt = $db->prepare("SELECT s.user_id, s.expires_at, u.role, u.full_name, u.email
                          FROM sessions s JOIN users u ON s.user_id = u.id
                          WHERE s.id = ? AND s.expires_at > NOW()");
    $stmt->execute([$token]);
    $session = $stmt->fetch();

    if (!$session) {
        respond(['error' => 'Session expired or invalid'], 401);
    }

    if (!empty($roles) && !in_array($session['role'], $roles)) {
        respond(['error' => 'Forbidden'], 403);
    }

    return $session;
}

function generateToken(int $length = 64): string {
    return bin2hex(random_bytes($length));
}

function auditLog(int $userId = null, string $action = '', string $entity = null, int $entityId = null, string $details = null): void {
    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$userId, $action, $entity, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}
