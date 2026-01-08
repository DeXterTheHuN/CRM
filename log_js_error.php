<?php
require_once 'config.php';

// Simple error handler - no complex error logging to avoid loops
@ini_set('display_errors', 0);
error_reporting(E_ALL);

// Manual auth check - API friendly
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Jelentkezz be!'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['message'])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Hiányzó hiba üzenet'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Prepare error data
    $errorData = [
        'error_type' => 'JS_ERROR',
        'severity' => $data['severity'] ?? 'ERROR',
        'message' => $data['message'] ?? 'Unknown JavaScript error',
        'file' => $data['file'] ?? null,
        'line' => $data['line'] ?? null,
        'trace' => $data['stack'] ?? null,
        'url' => $data['url'] ?? $_SERVER['REQUEST_URI'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'user_id' => $_SESSION['user_id'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'context' => json_encode([
            'browser' => $data['browser'] ?? 'unknown',
            'error_type' => $data['type'] ?? '',
            'column' => $data['column'] ?? null
        ], JSON_UNESCAPED_UNICODE)
    ];

    // Log to database
    $errorLogRepo = new ErrorLogRepository($pdo);
    $errorLogRepo->logError($errorData);

    // Also log to file for backup (simple error_log to avoid loop)
    error_log('JS Error: ' . $errorData['message'] . ' at ' . $errorData['file'] . ':' . $errorData['line']);

    echo json_encode(['success' => true, 'message' => 'Error logged successfully'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Error logging JS error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Hiba történt a JavaScript error naplózásakor'
    ], JSON_UNESCAPED_UNICODE);
}
