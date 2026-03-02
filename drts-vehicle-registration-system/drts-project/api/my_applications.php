<?php
// GET /api/my_applications.php
// Returns all applications for the authenticated user

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$session = requireAuth(['public', 'officer', 'admin']);
$db      = getDB();

$stmt = $db->prepare("
    SELECT
        a.id, a.reference_number, a.application_type, a.status,
        a.submitted_at, a.reviewed_at, a.approved_at, a.officer_notes,
        v.make, v.model, v.year, v.color, v.chassis_number, v.plate_number, v.fuel_type,
        vt.name AS vehicle_type,
        p.amount AS fee, p.status AS payment_status, p.paid_at,
        (SELECT COUNT(*) FROM documents d WHERE d.application_id = a.id) AS document_count
    FROM applications a
    JOIN vehicles v ON a.vehicle_id = v.id
    JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
    LEFT JOIN payments p ON p.application_id = a.id
    WHERE a.applicant_id = ?
    ORDER BY a.submitted_at DESC
");
$stmt->execute([$session['user_id']]);
$applications = $stmt->fetchAll();

respond(['applications' => $applications, 'count' => count($applications)]);
