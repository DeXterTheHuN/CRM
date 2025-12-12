<?php
require_once 'config.php';
ApiRoute::protect('auth');

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Instantiate approval notification repository
$approvalNotificationRepo = new ApprovalNotificationRepository($pdo);

try {
    switch ($action) {
        case 'get_unread':
            // Olvasatlan értesítések lekérdezése a repository segítségével
            $user_id = (int)$_SESSION['user_id'];
            $notifications = $approvalNotificationRepo->getUnreadByUser($user_id);
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ]);
            break;
            
        case 'mark_read':
            // Értesítés olvasottnak jelölése
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            $user_id = $_SESSION['user_id'];
            
            if ($notification_id) {
                $approvalNotificationRepo->markRead($notification_id, (int)$user_id);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Hiányzó értesítés ID']);
            }
            break;
            
        case 'mark_all_read':
            // Összes értesítés olvasottnak jelölése
            $user_id = (int)$_SESSION['user_id'];
            $approvalNotificationRepo->markAllRead($user_id);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Érvénytelen művelet']);
    }
    
} catch (PDOException $e) {
    error_log("Approval notifications API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Adatbázis hiba']);
}
