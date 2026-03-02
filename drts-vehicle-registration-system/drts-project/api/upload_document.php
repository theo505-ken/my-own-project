<?php
// POST /api/upload_document.php
// Handles document upload for a registration application

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { respond(['error' => 'Method not allowed'], 405); }

$session = requireAuth(['public', 'officer', 'admin']);

$applicationId = (int)($_POST['application_id'] ?? 0);
$documentType  = $_POST['document_type'] ?? '';

if (!$applicationId || empty($documentType)) {
    respond(['error' => 'application_id and document_type are required'], 422);
}

$validDocTypes = ['id_copy','proof_of_address','vehicle_photo','insurance','purchase_receipt','other'];
if (!in_array($documentType, $validDocTypes)) {
    respond(['error' => 'Invalid document type'], 422);
}

$db = getDB();

// Verify application belongs to user (unless officer/admin)
$stmt = $db->prepare("SELECT id, status FROM applications WHERE id = ? AND applicant_id = ?");
$stmt->execute([$applicationId, $session['user_id']]);
$app = $stmt->fetch();

if (!$app && !in_array($session['role'], ['officer', 'admin'])) {
    respond(['error' => 'Application not found'], 404);
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    respond(['error' => 'No file uploaded or upload error'], 422);
}

$file     = $_FILES['document'];
$mimeType = mime_content_type($file['tmp_name']);

if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
    respond(['error' => 'File type not allowed. Use JPEG, PNG, WEBP or PDF.'], 422);
}

if ($file['size'] > MAX_FILE_SIZE) {
    respond(['error' => 'File exceeds 5MB limit'], 422);
}

// Create upload directory
$uploadDir = UPLOAD_DIR . "application_$applicationId/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = $documentType . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$filePath = $uploadDir . $fileName;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    respond(['error' => 'Failed to save file'], 500);
}

$stmt = $db->prepare("INSERT INTO documents (application_id, document_type, file_name, file_path, file_size, mime_type)
                      VALUES (?,?,?,?,?,?)");
$stmt->execute([$applicationId, $documentType, $file['name'], $filePath, $file['size'], $mimeType]);
$docId = $db->lastInsertId();

auditLog($session['user_id'], 'DOCUMENT_UPLOADED', 'documents', $docId, "Type: $documentType for app #$applicationId");

respond(['message' => 'Document uploaded successfully', 'document_id' => (int)$docId], 201);
