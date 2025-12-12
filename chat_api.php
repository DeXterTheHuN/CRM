<?php
require_once 'config.php';
ApiRoute::protect('auth');

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'];

// Instantiate chat repository
$chatRepo = new ChatRepository($pdo);

try {
    switch ($action) {
        case 'get_messages':
            $last_id = $_GET['last_id'] ?? 0;
            $messages = $chatRepo->getMessages((int)$last_id, 50);
            ApiResponse::success(['messages' => $messages]);
            break;
            
        case 'send_message':
            $message = trim($_POST['message'] ?? '');
            
            // Validáció
            if (empty($message)) {
                ApiResponse::validationError(['message' => 'Az üzenet nem lehet üres']);
            }
            
            if (strlen($message) > 2000) {
                ApiResponse::validationError(['message' => 'Az üzenet túl hosszú (max 2000 karakter)']);
            }
            
            $messageId = $chatRepo->sendMessage((int)$userId, (string)$userName, $message);
            ApiResponse::success(['message_id' => $messageId], 'Üzenet elküldve');
            break;
            
        case 'mark_read':
            $last_id = $_GET['last_id'] ?? 0;
            $chatRepo->markRead((int)$userId, (int)$last_id);
            ApiResponse::success();
            break;
            
        case 'get_unread_count':
            $unreadCount = $chatRepo->getUnreadCount((int)$userId);
            ApiResponse::success(['unread_count' => $unreadCount]);
            break;
            
        default:
            ApiResponse::error('Érvénytelen művelet');
    }
} catch (PDOException $e) {
    ApiResponse::error(
        'Adatbázis hiba történt',
        'Database error in chat_api: ' . $e->getMessage(),
        500,
        ['action' => $action, 'user_id' => $userId]
    );
} catch (Exception $e) {
    ApiResponse::error(
        'Váratlan hiba történt',
        'Unexpected error in chat_api: ' . $e->getMessage(),
        500
    );
}
