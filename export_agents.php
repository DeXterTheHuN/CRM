<?php
require_once 'config.php';
require_once 'helpers/ExportHelper.php';
Route::protect('admin');

// Fetch agent statistics
$sql = "SELECT 
    a.id,
    a.name as agent_name,
    COUNT(c.id) as total_clients,
    SUM(CASE WHEN c.closed_at IS NULL THEN 1 ELSE 0 END) as active_clients,
    SUM(CASE WHEN c.work_completed = 1 THEN 1 ELSE 0 END) as completed_clients,
    SUM(CASE WHEN c.contract_signed = 1 THEN 1 ELSE 0 END) as signed_contracts,
    ROUND((SUM(CASE WHEN c.work_completed = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(c.id), 0)) * 100, 2) as success_rate,
    MAX(c.created_at) as last_activity
FROM agents a
LEFT JOIN clients c ON a.id = c.agent_id
GROUP BY a.id, a.name
ORDER BY total_clients DESC";

$stmt = $pdo->query($sql);
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare export data
$headers = [
    'Ügyintéző neve',
    'Összes ügyfél',
    'Aktív ügyfelek',
    'Befejezett munka',
    'Aláírt szerződés',
    'Sikerességi arány (%)',
    'Utolsó aktivitás'
];

$data = [];
foreach ($agents as $agent) {
    $data[] = [
        $agent['agent_name'],
        $agent['total_clients'],
        $agent['active_clients'],
        $agent['completed_clients'],
        $agent['signed_contracts'],
        $agent['success_rate'] . '%',
        $agent['last_activity'] ? date('Y-m-d H:i', strtotime($agent['last_activity'])) : '-'
    ];
}

// Generate filename
$filename = ExportHelper::generateFilename('ugyintezok');

// Log export
require_once 'audit_helper.php';
logDataExport($pdo, 'agents', count($agents), []);

// Export to CSV
ExportHelper::exportToCSV($filename, $headers, $data);
