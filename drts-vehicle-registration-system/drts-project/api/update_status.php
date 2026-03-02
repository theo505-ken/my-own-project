<?php
// POST /api/update_status.php
// Officer/admin updates application status (approve/reject/under_review)

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { respond(['error' => 'Method not allowed'], 405); }

$session = requireAuth(['officer', 'admin']);
$body    = json_decode(file_get_contents('php://input'), true);

$applicationId = (int)($body['application_id'] ?? 0);
$newStatus     = $body['status'] ?? '';
$notes         = trim($body['notes'] ?? '');

if (!$applicationId || empty($newStatus)) {
    respond(['error' => 'application_id and status are required'], 422);
}

$allowedStatuses = ['under_review', 'approved', 'rejected'];
if (!in_array($newStatus, $allowedStatuses)) {
    respond(['error' => 'Invalid status. Must be: under_review, approved, or rejected'], 422);
}

$db = getDB();

$stmt = $db->prepare("SELECT id, status, reference_number FROM applications WHERE id = ?");
$stmt->execute([$applicationId]);
$app = $stmt->fetch();

if (!$app) {
    respond(['error' => 'Application not found'], 404);
}

// Prevent rolling back a paid application
if ($app['status'] === 'paid') {
    respond(['error' => 'Cannot change status of a completed (paid) application'], 400);
}

$approvedAt  = $newStatus === 'approved' ? 'NOW()' : 'NULL';
$reviewedAt  = 'NOW()';

$stmt = $db->prepare("
    UPDATE applications
    SET status=?, officer_id=?, officer_notes=?, reviewed_at=NOW(),
        approved_at=" . ($newStatus === 'approved' ? 'NOW()' : 'NULL') . "
    WHERE id=?
");
$stmt->execute([$newStatus, $session['user_id'], $notes ?: null, $applicationId]);

auditLog(
    $session['user_id'],
    'APPLICATION_STATUS_UPDATED',
    'applications',
    $applicationId,
    "Status: {$app['status']} → $newStatus | Ref: {$app['reference_number']}"
);

respond([
    'message'   => "Application status updated to '$newStatus'.",
    'reference' => $app['reference_number'],
    'status'    => $newStatus,
]);
