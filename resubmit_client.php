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

// POST method ellenőrzés
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Érvénytelen kérés'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Bemeneti adatok
$client_id = $_POST['id'] ?? 0;
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$county_id = $_POST['county_id'] ?? 0;
$settlement_id = $_POST['settlement_id'] ?? 0;
$agent_id = $_POST['agent_id'] ?? null;
$insulation_area = $_POST['insulation_area'] ?? null;
$contract_signed = $_POST['contract_signed'] ?? 0;
$work_completed = $_POST['work_completed'] ?? 0;
$notes = trim($_POST['notes'] ?? '');

// Validáció
if (empty($name)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Validációs hiba',
        'errors' => ['name' => 'A név megadása kötelező']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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
    // Instantiate client repository
    $clientRepo = new ClientRepository($pdo);

    // Ellenőrizzük hogy az ügyfél létezik és elutasított státuszú
    $client = $clientRepo->getClientById($client_id);

    if (!$client) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Az ügyfél nem található'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Csak a saját elutasított ügyfelét küldheti újra
    if ($client['created_by'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Csak a saját ügyfeleidet küldheted újra'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($client['approval_status'] !== 'rejected') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Csak elutasított ügyfelet küldhetsz újra'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // contract_signed_at és closed_at kezelése
    $contract_signed_at = $client['contract_signed_at']; // ✅ Alapból a meglévő érték
    $closed_at = $client['closed_at']; // ✅ Alapból a meglévő érték

    if ($contract_signed) {
        // Ha nincs még dátum, most állítjuk be
        if (!$contract_signed_at) {
            $contract_signed_at = date('Y-m-d H:i:s');
        }

        if ($work_completed) {
            // Ha nincs még closed_at, most állítjuk be
            if (!$closed_at) {
                $closed_at = date('Y-m-d H:i:s');
            }
        }
    }
    // Ha bármelyik pipa ki van kapcsolva, a meglévő dátumok megmaradnak

    // Adatok összeállítása frissítéshez
    $fields = [
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'address' => $address,
        'county_id' => $county_id,
        'settlement_id' => $settlement_id ?: null,
        'agent_id' => $agent_id ?: null,
        'insulation_area' => $insulation_area ?: null,
        'contract_signed' => $contract_signed,
        'work_completed' => $work_completed,
        'notes' => $notes,
        'contract_signed_at' => $contract_signed_at,
        'closed_at' => $closed_at,
        'approval_status' => 'pending',
        'approved_at' => null,
        'approved_by' => null,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Frissítés a repository-val
    $clientRepo->updateClient($client_id, $fields);

    // Cache invalidálás - ügyfél státusza változott (approved/rejected -> pending)
    cache_delete('counties_with_counts');

    echo json_encode(['success' => true, 'message' => 'Ügyfél sikeresen újraküldve jóváhagyásra!'], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('Database error in resubmit_client: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Adatbázis hiba történt'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Unexpected error in resubmit_client: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Váratlan hiba történt'], JSON_UNESCAPED_UNICODE);
}
