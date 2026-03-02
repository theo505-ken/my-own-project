<?php
// GET /api/check_status.php?ref=DRTS-2026-XXXXXX
// Returns the status of an application by reference number

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$ref = trim($_GET['ref'] ?? '');
if (empty($ref)) {
    respond(['error' => 'Reference number is required'], 422);
}

$db   = getDB();
$stmt = $db->prepare("
    SELECT
        a.reference_number, a.application_type, a.status,
        a.submitted_at, a.reviewed_at, a.approved_at,
        a.officer_notes,
        v.make, v.model, v.year, v.plate_number,
        p.amount AS fee, p.status AS payment_status
    FROM applications a
    JOIN vehicles v ON a.vehicle_id = v.id
    LEFT JOIN payments p ON p.application_id = a.id
    WHERE a.reference_number = ?
");
$stmt->execute([$ref]);
$app = $stmt->fetch();

if (!$app) {
    respond(['error' => 'Application not found'], 404);
}

respond(['application' => $app]);
