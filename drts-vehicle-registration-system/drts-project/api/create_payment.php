<?php
// POST /api/create_payment.php
// Creates a Stripe PaymentIntent for an approved application

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { respond(['error' => 'Method not allowed'], 405); }

$session = requireAuth(['public', 'officer', 'admin']);
$body    = json_decode(file_get_contents('php://input'), true);

$applicationId = (int)($body['application_id'] ?? 0);
if (!$applicationId) {
    respond(['error' => 'application_id is required'], 422);
}

$db = getDB();

// Fetch application and payment details
$stmt = $db->prepare("
    SELECT a.id, a.status, a.reference_number, a.applicant_id,
           p.id AS payment_id, p.amount, p.status AS payment_status, p.stripe_payment_intent_id
    FROM applications a
    JOIN payments p ON p.application_id = a.id
    WHERE a.id = ? AND a.applicant_id = ?
");
$stmt->execute([$applicationId, $session['user_id']]);
$record = $stmt->fetch();

if (!$record) {
    respond(['error' => 'Application not found'], 404);
}

if ($record['status'] !== 'approved') {
    respond(['error' => 'Application must be approved before payment'], 400);
}

if ($record['payment_status'] === 'completed') {
    respond(['error' => 'Payment already completed'], 400);
}

// Call Stripe API to create a PaymentIntent
$stripePayload = json_encode([
    'amount'   => (int)($record['amount'] * 100), // cents
    'currency' => 'bwp',
    'metadata' => [
        'application_id'   => $applicationId,
        'reference_number' => $record['reference_number'],
    ],
    'automatic_payment_methods' => ['enabled' => true],
]);

$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $stripePayload,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
        'Content-Type: application/json',
    ],
]);
$stripeResponse = curl_exec($ch);
$httpCode       = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$stripeData = json_decode($stripeResponse, true);

if ($httpCode !== 200 || empty($stripeData['id'])) {
    // In development/test mode, simulate a payment intent
    $simulatedId = 'pi_simulated_' . bin2hex(random_bytes(8));
    $stmt = $db->prepare("UPDATE payments SET stripe_payment_intent_id=?, status='processing' WHERE application_id=?");
    $stmt->execute([$simulatedId, $applicationId]);

    respond([
        'client_secret'      => $simulatedId . '_secret_simulated',
        'payment_intent_id'  => $simulatedId,
        'amount'             => (float)$record['amount'],
        'currency'           => 'BWP',
        'stripe_public_key'  => STRIPE_PUBLIC_KEY,
        'note'               => 'Simulated payment intent (configure Stripe keys for live payments)',
    ]);
}

// Real Stripe response
$stmt = $db->prepare("UPDATE payments SET stripe_payment_intent_id=?, status='processing' WHERE application_id=?");
$stmt->execute([$stripeData['id'], $applicationId]);

respond([
    'client_secret'     => $stripeData['client_secret'],
    'payment_intent_id' => $stripeData['id'],
    'amount'            => (float)$record['amount'],
    'currency'          => 'BWP',
    'stripe_public_key' => STRIPE_PUBLIC_KEY,
]);
