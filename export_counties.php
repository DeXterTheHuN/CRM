<?php
require_once 'config.php';
require_once 'helpers/ExportHelper.php';
Route::protect('admin');

// Get parameters
$county_id = isset($_GET['county_id']) ? (int) $_GET['county_id'] : 0;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$include_closed = isset($_GET['include_closed']) ? (bool) $_GET['include_closed'] : true;

// Build query
$whereClauses = ['1=1'];
$params = [];

if ($county_id > 0) {
    $whereClauses[] = "co.id = ?";
    $params[] = $county_id;
}

if ($date_from) {
    $whereClauses[] = "c.created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $whereClauses[] = "c.created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if (!$include_closed) {
    $whereClauses[] = "c.closed_at IS NULL";
}

$whereSQL = implode(' AND ', $whereClauses);

// Fetch data
$sql = "SELECT 
    co.name as county,
    c.name as client_name,
    s.name as settlement,
    IFNULL(a.name, '-') as agent,
    c.approval_status as status,
    IF(c.contract_signed, 'Igen', 'Nem') as contract_signed,
    IF(c.work_completed, 'Igen', 'Nem') as work_completed,
    c.phone,
    c.email,
    c.address,
    DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i') as created_date,
    IFNULL(DATE_FORMAT(c.closed_at, '%Y-%m-%d %H:%i'), '-') as closed_date
FROM clients c
JOIN settlements s ON c.settlement_id = s.id
JOIN counties co ON s.county_id = co.id
LEFT JOIN agents a ON c.agent_id = a.id
WHERE {$whereSQL}
ORDER BY co.name, c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare export data
$headers = [
    'Megye',
    'Ügyfél neve',
    'Település',
    'Ügyintéző',
    'Státusz',
    'Szerződés aláírva',
    'Munka befejezve',
    'Telefon',
    'Email',
    'Cím',
    'Létrehozva',
    'Lezárva'
];

$data = [];
foreach ($clients as $client) {
    $data[] = [
        $client['county'],
        $client['client_name'],
        $client['settlement'],
        $client['agent'],
        $client['status'],
        $client['contract_signed'],
        $client['work_completed'],
        $client['phone'],
        $client['email'],
        $client['address'],
        $client['created_date'],
        $client['closed_date']
    ];
}

// Generate filename
$countyName = $county_id > 0 ? 'megye_' . $county_id : 'osszes_megye';
$filename = ExportHelper::generateFilename('ugyfelek_' . $countyName);

// Log export
require_once 'audit_helper.php';
logDataExport($pdo, 'clients', count($clients), ['county_id' => $county_id]);

// Export to CSV
ExportHelper::exportToCSV($filename, $headers, $data);
