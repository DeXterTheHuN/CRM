<?php
// audit_log_export.php
require_once 'config.php';
require_once 'audit_helper.php';
Route::protect('admin');

// Szűrési paraméterek
$filters = [
    'user' => $_GET['filter_user'] ?? '',
    'action' => $_GET['filter_action'] ?? '',
    'table' => $_GET['filter_table'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => '' // search is not used for export
];

// Use AuditLogRepository to fetch all logs matching filters
$auditRepo = new AuditLogRepository($pdo);
$logs = $auditRepo->exportAuditLogs($filters);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_H-i-s') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['Időpont', 'Felhasználó', 'Művelet', 'Tábla', 'Rekord ID', 'IP cím', 'Régi érték', 'Új érték']);

foreach ($logs as $log) {
    fputcsv($output, [
        $log['created_at'],
        $log['user_name'],
        $log['action'],
        $log['table_name'],
        $log['record_id'],
        $log['ip_address'],
        $log['old_values'],
        $log['new_values']
    ]);
}

fclose($output);

// Audit log
logDataExport($pdo, 'audit_logs', count($logs), $filters);
