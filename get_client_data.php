<?php
require_once 'config.php';
ApiRoute::protect('auth');

header('Content-Type: application/json');

// Ügyfél ID validálása
$client_id = $_GET['id'] ?? 0;
if (!$client_id) {
    ApiResponse::validationError(['id' => 'Hiányzó ügyfél ID']);
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
        ApiResponse::notFound('Az ügyfél nem található');
    }

    // Jogosultság ellenőrzése
    $user_id = $_SESSION['user_id'];
    $is_admin = isAdmin();

    // Ellenőrizzük, hogy a felhasználó megtekintheti-e az ügyfelet
    $can_view = $clientRepo->canUserViewClient((int) $client_id, $user_id, $is_admin);

    if (!$can_view) {
        // Részletes naplózás
        ErrorHandler::logAppError('Unauthorized client data access', [
            'client_id' => $client_id,
            'user_id' => $user_id,
            'is_admin' => $is_admin,
            'client_approved' => ($client['approval_status'] === 'approved'),
            'client_agent_id' => $client['agent_id']
        ]);

        ApiResponse::unauthorized('Nincs jogosultságod ehhez az ügyfélhez');
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
    ApiResponse::error(
        'Adatbázis hiba történt',
        'Database error in get_client_data: ' . $e->getMessage(),
        500,
        ['client_id' => $client_id, 'user_id' => $_SESSION['user_id']]
    );
} catch (Exception $e) {
    ApiResponse::error(
        'Váratlan hiba történt',
        'Unexpected error in get_client_data: ' . $e->getMessage(),
        500
    );
}
