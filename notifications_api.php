<?php
require_once 'config.php';

// Simple error handler - no complex error logging to avoid loops
@ini_set('display_errors', 0);
error_reporting(E_ALL);

// Manual auth check - API friendly
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$isAdmin = isAdmin();

try {
    // Instantiate repositories
    $notificationRepo = new NotificationRepository($pdo);
    $clientRepo = new ClientRepository($pdo);

    switch ($action) {
        case 'get_counts':
            // Értesítési számlálók lekérése repositoryk segítségével
            $approvalCount = $isAdmin ? $notificationRepo->getPendingApprovalsCount() : 0;
            $newClientsCount = $notificationRepo->getNewClientsTotal((int) $userId, $isAdmin);
            $newByCounty = $notificationRepo->getNewClientsByCounty((int) $userId, $isAdmin);
            echo json_encode([
                'success' => true,
                'approvals_pending' => (int) $approvalCount,
                'new_clients_total' => (int) $newClientsCount,
                'new_clients_by_county' => $newByCounty
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'mark_client_viewed':
            $clientId = $_POST['client_id'] ?? 0;
            if ($clientId <= 0) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'Validációs hiba',
                    'errors' => ['client_id' => 'Invalid client ID']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Ellenőrizzük, hogy az ügyfél létezik és approved státuszú
            // (A megtekintettség jelölés nem érzékeny művelet, 
            // és az ügyfél már látható a county oldalon)
            $client = $clientRepo->getClientById((int) $clientId);
            if (!$client || $client['approval_status'] !== 'approved') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Client not found or not approved'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Megtekintettnek jelöljük a NotificationRepository használatával
            $notificationRepo->markClientViewed((int) $clientId, (int) $userId);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'mark_county_clients_viewed':
            $countyId = $_POST['county_id'] ?? 0;
            if ($countyId <= 0) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'Validációs hiba',
                    'errors' => ['county_id' => 'Invalid county ID']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $markedCount = $notificationRepo->markCountyClientsViewed((int) $countyId, (int) $userId, $isAdmin);
            echo json_encode(['success' => true, 'data' => ['marked_count' => $markedCount]], JSON_UNESCAPED_UNICODE);
            break;



        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
