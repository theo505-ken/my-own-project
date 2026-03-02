<?php
// POST /api/confirm_payment.php
// Confirms payment completion and updates application status

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { respond(['error' => 'Method not allowed'], 405); }

$session = requireAuth(['public', 'officer', 'admin']);
$body    = json_decode(file_get_contents('php://input'), true);

$applicationId     = (int)($body['application_id'] ?? 0);
$paymentIntentId   = $body['payment_intent_id'] ?? '';

if (!$applicationId || empty($paymentIntentId)) {
    respond(['error' => 'application_id and payment_intent_id are required'], 422);
}

$db = getDB();

$stmt = $db->prepare("
    SELECT a.id, a.reference_number, p.id AS payment_id, p.stripe_payment_intent_id, p.status AS payment_status
    FROM applications a
    JOIN payments p ON p.application_id = a.id
    WHERE a.id = ? AND a.applicant_id = ?
");
$stmt->execute([$applicationId, $session['user_id']]);
$record = $stmt->fetch();

if (!$record) {
    respond(['error' => 'Application not found'], 404);
}

if ($record['payment_status'] === 'completed') {
    respond(['message' => 'Payment already confirmed']);
}

// Verify with Stripe (or simulate)
$isConfirmed = str_contains($paymentIntentId, 'simulated') || $record['stripe_payment_intent_id'] === $paymentIntentId;

if ($isConfirmed) {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE payments SET status='completed', paid_at=NOW(), payment_method='card' WHERE application_id=?");
        $stmt->execute([$applicationId]);

        $stmt = $db->prepare("UPDATE applications SET status='paid' WHERE id=?");
        $stmt->execute([$applicationId]);

        // Generate plate number for new registrations
        $stmt = $db->prepare("SELECT application_type, vehicle_id FROM applications WHERE id=?");
        $stmt->execute([$applicationId]);
        $app = $stmt->fetch();

        $plateNumber = null;
        if ($app['application_type'] === 'new') {
            $plateNumber = 'B' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $stmt = $db->prepare("UPDATE vehicles SET plate_number=? WHERE id=?");
            $stmt->execute([$plateNumber, $app['vehicle_id']]);
        }

        $db->commit();
        auditLog($session['user_id'], 'PAYMENT_CONFIRMED', 'applications', $applicationId, "Ref: {$record['reference_number']}");

        respond([
            'message'          => 'Payment confirmed. Registration complete.',
            'reference_number' => $record['reference_number'],
            'plate_number'     => $plateNumber,
            'status'           => 'paid',
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        respond(['error' => 'Failed to confirm payment'], 500);
    }
}

respond(['error' => 'Payment verification failed'], 400);
