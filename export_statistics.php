<?php
require_once 'config.php';
require_once 'helpers/ExportHelper.php';
Route::protect('admin');

// Fetch statistics data

// 1. Overall statistics
$overallSql = "SELECT 
    COUNT(*) as total_clients,
    SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN contract_signed = 1 THEN 1 ELSE 0 END) as contract_signed,
    SUM(CASE WHEN contract_signed = 0 THEN 1 ELSE 0 END) as contract_not_signed,
    SUM(CASE WHEN work_completed = 1 THEN 1 ELSE 0 END) as work_completed,
    SUM(CASE WHEN work_completed = 0 THEN 1 ELSE 0 END) as work_in_progress,
    SUM(CASE WHEN contract_signed = 1 AND work_completed = 0 THEN 1 ELSE 0 END) as contract_only,
    SUM(CASE WHEN contract_signed = 1 AND work_completed = 1 THEN 1 ELSE 0 END) as both_completed
FROM clients";

$overall = $pdo->query($overallSql)->fetch(PDO::FETCH_ASSOC);

// 2. Statistics by county
$countySql = "SELECT 
    co.name as county,
    COUNT(c.id) as total_clients,
    SUM(CASE WHEN c.contract_signed = 1 THEN 1 ELSE 0 END) as contract_signed,
    SUM(CASE WHEN c.work_completed = 1 THEN 1 ELSE 0 END) as work_completed,
    SUM(CASE WHEN c.contract_signed = 1 AND c.work_completed = 0 THEN 1 ELSE 0 END) as contract_only,
    SUM(CASE WHEN c.contract_signed = 1 AND c.work_completed = 1 THEN 1 ELSE 0 END) as both_done
FROM clients c
JOIN settlements s ON c.settlement_id = s.id
JOIN counties co ON s.county_id = co.id
GROUP BY co.id, co.name
ORDER BY total_clients DESC";

$counties = $pdo->query($countySql)->fetchAll(PDO::FETCH_ASSOC);

// Prepare export data
$headers = ['Kategória', 'Érték'];
$data = [];

// Overall section
$data[] = ['=== ÖSSZESÍTÉS ===', ''];
$data[] = ['Összes ügyfél', $overall['total_clients']];
$data[] = ['', ''];
$data[] = ['=== JÓVÁHAGYÁSI STÁTUSZ ===', ''];
$data[] = ['Várakozó', $overall['pending']];
$data[] = ['Jóváhagyott', $overall['approved']];
$data[] = ['Elutasított', $overall['rejected']];
$data[] = ['', ''];
$data[] = ['=== SZERZŐDÉS STÁTUSZ ===', ''];
$data[] = ['Szerződés aláírva', $overall['contract_signed']];
$data[] = ['Szerződés nincs aláírva', $overall['contract_not_signed']];
$data[] = ['', ''];
$data[] = ['=== MUNKA STÁTUSZ ===', ''];
$data[] = ['Munka befejezve', $overall['work_completed']];
$data[] = ['Munka folyamatban', $overall['work_in_progress']];
$data[] = ['', ''];
$data[] = ['=== KOMBINÁLT STÁTUSZ ===', ''];
$data[] = ['Csak szerződés (munka nincs kész)', $overall['contract_only']];
$data[] = ['Szerződés ÉS munka kész', $overall['both_completed']];
$data[] = ['', ''];
$data[] = ['', ''];
$data[] = ['=== STATISZTIKA MEGYÉNKÉNT ===', ''];
$data[] = ['Megye', 'Összes', 'Szerződés', 'Munka kész', 'Csak szerződés', 'Mind kész'];

foreach ($counties as $county) {
    $data[] = [
        $county['county'],
        $county['total_clients'],
        $county['contract_signed'],
        $county['work_completed'],
        $county['contract_only'],
        $county['both_done']
    ];
}

// Generate filename
$filename = ExportHelper::generateFilename('statisztikak');

// Log export
require_once 'audit_helper.php';
logDataExport($pdo, 'statistics', count($data), []);

// Export to CSV
ExportHelper::exportToCSV($filename, $headers, $data);
