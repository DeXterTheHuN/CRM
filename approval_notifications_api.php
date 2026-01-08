<?php
require_once 'config.php';

// Simple error handler - no complex error logging to avoid loops
@ini_set('display_errors', 0);
error_reporting(E_ALL);

// Manual auth check - API friendly (no redirect loop)
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

$action = $_GET['action'] ?? '';

try {
    // Instantiate approval notification repository
    $approvalNotificationRepo = new ApprovalNotificationRepository($pdo);

    switch ($action) {
        case 'get_unread':
            // Olvasatlan értesítések lekérdezése a repository segítségével
            $user_id = (int) $_SESSION['user_id'];
            $notifications = $approvalNotificationRepo->getUnreadByUser($user_id);
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'mark_read':
            // Értesítés olvasottnak jelölése
            $notification_id = (int) ($_POST['notification_id'] ?? 0);
            $user_id = $_SESSION['user_id'];

            if ($notification_id <= 0) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'Validációs hiba',
                    'errors' => ['notification_id' => 'Hiányzó értesítés ID']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $approvalNotificationRepo->markRead($notification_id, (int) $user_id);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'mark_all_read':
            // Összes értesítés olvasottnak jelölése
            $user_id = (int) $_SESSION['user_id'];
            $approvalNotificationRepo->markAllRead($user_id);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Érvénytelen művelet'
            ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    error_log("Approval notifications API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Adatbázis hiba'
    ], JSON_UNESCAPED_UNICODE);
}
