<?php
// POST /api/register.php
// Registers a new public user account

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { respond(['error' => 'Method not allowed'], 405); }

$body = json_decode(file_get_contents('php://input'), true);

$required = ['full_name', 'email', 'password', 'phone', 'national_id'];
foreach ($required as $field) {
    if (empty($body[$field])) {
        respond(['error' => "Missing field: $field"], 422);
    }
}

// Validate email
if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
    respond(['error' => 'Invalid email address'], 422);
}

// Validate password strength
if (strlen($body['password']) < 8) {
    respond(['error' => 'Password must be at least 8 characters'], 422);
}

$db = getDB();

// Check duplicates
$stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR national_id = ?");
$stmt->execute([$body['email'], $body['national_id']]);
if ($stmt->fetch()) {
    respond(['error' => 'An account with this email or national ID already exists'], 409);
}

$hash = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $db->prepare("INSERT INTO users (full_name, email, password_hash, phone, national_id, address, role)
                      VALUES (?, ?, ?, ?, ?, ?, 'public')");
$stmt->execute([
    trim($body['full_name']),
    strtolower(trim($body['email'])),
    $hash,
    $body['phone'],
    strtoupper(trim($body['national_id'])),
    $body['address'] ?? null,
]);

$userId = $db->lastInsertId();
auditLog($userId, 'USER_REGISTERED', 'users', $userId, 'New public user registered');

respond(['message' => 'Account created successfully. Please log in.', 'user_id' => (int)$userId], 201);
