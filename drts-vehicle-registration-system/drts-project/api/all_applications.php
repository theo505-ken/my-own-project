<?php
// GET /api/all_applications.php
// Returns all applications (officers and admins only)
// Supports filtering: ?status=pending&type=new&search=DRTS-2026

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$session = requireAuth(['officer', 'admin']);
$db      = getDB();

$where  = ['1=1'];
$params = [];

// Status filter
if (!empty($_GET['status'])) {
    $where[]  = 'a.status = ?';
    $params[] = $_GET['status'];
}

// Type filter
if (!empty($_GET['type'])) {
    $where[]  = 'a.application_type = ?';
    $params[] = $_GET['type'];
}

// Search by reference number, chassis, or owner name
if (!empty($_GET['search'])) {
    $like     = '%' . $_GET['search'] . '%';
    $where[]  = '(a.reference_number LIKE ? OR v.chassis_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
    $params   = array_merge($params, [$like, $like, $like, $like]);
}

$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT
        a.id, a.reference_number, a.application_type, a.status,
        a.submitted_at, a.reviewed_at, a.approved_at, a.officer_notes,
        v.make, v.model, v.year, v.color, v.chassis_number, v.plate_number, v.fuel_type,
        vt.name AS vehicle_type,
        u.id AS owner_id, u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone,
        p.amount AS fee, p.status AS payment_status, p.paid_at,
        (SELECT COUNT(*) FROM documents d WHERE d.application_id = a.id) AS document_count,
        o.full_name AS reviewing_officer
    FROM applications a
    JOIN vehicles v ON a.vehicle_id = v.id
    JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
    JOIN users u ON a.applicant_id = u.id
    LEFT JOIN payments p ON p.application_id = a.id
    LEFT JOIN users o ON a.officer_id = o.id
    WHERE $whereClause
    ORDER BY a.submitted_at DESC
    LIMIT 200
");
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Summary stats
$statsStmt = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(status='pending') AS pending,
        SUM(status='under_review') AS under_review,
        SUM(status='approved') AS approved,
        SUM(status='rejected') AS rejected,
        SUM(status='paid') AS paid
    FROM applications
");
$stats = $statsStmt->fetch();

respond([
    'applications' => $applications,
    'count'        => count($applications),
    'stats'        => $stats,
]);
