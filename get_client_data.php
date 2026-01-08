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

// Ügyfél ID validálása
$client_id = $_GET['id'] ?? 0;
if (!$client_id) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Validációs hiba',
        'errors' => ['id' => 'Hiányzó ügyfél ID']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Instantiate repositories
    $clientRepo = new ClientRepository($pdo);
    $countyRepo = new CountyRepository($pdo);
    $settlementRepo = new SettlementRepository($pdo);
    $agentRepo = new AgentRepository($pdo);

    // Ügyfél lekérdezése
    $client = $clientRepo->getClientByIdWithSettlement((int) $client_id);

    if (!$client) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Az ügyfél nem található'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Jogosultság ellenőrzése
    $user_id = $_SESSION['user_id'];
    $is_admin = isAdmin();

    // Ügyintézőknél ellenőrizzük, hogy sajátja-e vagy nincs ügyintéző hozzárendelve
    if (!$is_admin) {
        $current_user_agent_id = $agentRepo->getAgentIdByName($_SESSION['name']);
        $can_edit = ($current_user_agent_id && ($client['agent_id'] === null || $client['agent_id'] == $current_user_agent_id));

        if (!$can_edit) {
            // Részletes naplózás
            error_log('Unauthorized client data access - client_id: ' . $client_id . ', user_id: ' . $user_id);

            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Nincs jogosultságod ehhez az ügyfélhez'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Megyék lekérdezése
    $counties = $countyRepo->getAll();

    // Települések lekérdezése az adott megyéhez
    $settlements = $settlementRepo->getSettlementsByCounty($client['county_id']);

    // Ügyintézők lekérdezése
    $agents = $agentRepo->getActiveAgents();

    // SPECIÁLIS: Visszafelé kompatibilis JSON formátum a JS-hez
    // A frontend county.php bulkEdit funkció erre a formátumra van kódolva
    echo json_encode([
        'success' => true,
        'client' => $client,
        'counties' => $counties,
        'settlements' => $settlements,
        'agents' => $agents
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    error_log('Database error in get_client_data: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Adatbázis hiba történt'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Unexpected error in get_client_data: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Váratlan hiba történt'], JSON_UNESCAPED_UNICODE);
}
