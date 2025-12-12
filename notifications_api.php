<?php
require_once 'config.php';
ApiRoute::protect('auth');

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$isAdmin = isAdmin();

// Instantiate repositories
$notificationRepo = new NotificationRepository($pdo);
$clientRepo = new ClientRepository($pdo);

try {
    switch ($action) {
        case 'get_counts':
            // Értesítési számlálók lekérése repositoryk segítségével
            $chatCount = $notificationRepo->getUnreadChatCount((int)$userId);
            $approvalCount = $isAdmin ? $notificationRepo->getPendingApprovalsCount() : 0;
            $newClientsCount = $notificationRepo->getNewClientsTotal((int)$userId, $isAdmin);
            $newByCounty = $notificationRepo->getNewClientsByCounty((int)$userId, $isAdmin);
            echo json_encode([
                'success' => true,
                'chat_unread' => (int)$chatCount,
                'approvals_pending' => (int)$approvalCount,
                'new_clients_total' => (int)$newClientsCount,
                'new_clients_by_county' => $newByCounty
            ]);
            break;
            
        case 'mark_client_viewed':
            $clientId = $_POST['client_id'] ?? 0;
            if ($clientId > 0) {
                // Ellenőrizzük, hogy a felhasználó láthatja-e az ügyfelet a ClientRepository segítségével
                if ($clientRepo->canUserViewClient((int)$clientId, (int)$userId, $isAdmin)) {
                    // Megtekintettnek jelöljük a NotificationRepository használatával
                    $notificationRepo->markClientViewed((int)$clientId, (int)$userId);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid client ID']);
            }
            break;
            
        case 'mark_county_clients_viewed':
            $countyId = $_POST['county_id'] ?? 0;
            if ($countyId > 0) {
                $markedCount = $notificationRepo->markCountyClientsViewed((int)$countyId, (int)$userId, $isAdmin);
                echo json_encode(['success' => true, 'marked_count' => $markedCount]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid county ID']);
            }
            break;
            
        case 'get_latest_chat_message':
            // Legutóbbi üzenet lekérése (toast értesítéshez)
            $lastCheckTime = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-10 seconds'));
            $latest = $notificationRepo->getLatestChatMessage((int)$userId, $lastCheckTime);
            if ($latest) {
                echo json_encode([
                    'success' => true,
                    'has_new' => true,
                    'message' => [
                        'id' => $latest['id'],
                        'user_name' => $latest['user_name'],
                        'message' => mb_substr($latest['message'], 0, 50) . (mb_strlen($latest['message']) > 50 ? '...' : ''),
                        'created_at' => $latest['created_at']
                    ]
                ]);
            } else {
                echo json_encode(['success' => true, 'has_new' => false]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
