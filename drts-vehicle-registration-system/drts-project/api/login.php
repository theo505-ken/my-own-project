<?php
// POST /api/login.php
// Authenticates user and returns a session token

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { respond(['error' => 'Method not allowed'], 405); }

$body = json_decode(file_get_contents('php://input'), true);

if (empty($body['email']) || empty($body['password'])) {
    respond(['error' => 'Email and password are required'], 422);
}

$db   = getDB();
$stmt = $db->prepare("SELECT id, full_name, email, password_hash, role, phone FROM users WHERE email = ?");
$stmt->execute([strtolower(trim($body['email']))]);
$user = $stmt->fetch();

if (!$user || !password_verify($body['password'], $user['password_hash'])) {
    respond(['error' => 'Invalid email or password'], 401);
}

// Create session
$token     = generateToken();
$expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

$stmt = $db->prepare("INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?,?,?,?,?)");
$stmt->execute([
    $token,
    $user['id'],
    $_SERVER['REMOTE_ADDR'] ?? null,
    $_SERVER['HTTP_USER_AGENT'] ?? null,
    $expiresAt,
]);

auditLog($user['id'], 'USER_LOGIN', 'users', $user['id'], 'Login successful');

respond([
    'token'   => $token,
    'expires' => $expiresAt,
    'user'    => [
        'id'        => (int)$user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'phone'     => $user['phone'],
    ]
]);
