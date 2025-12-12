<?php
require_once 'config.php';
ApiRoute::protect('auth');

// Instantiate client repository
$clientRepo = new ClientRepository($pdo);

header('Content-Type: application/json');

// POST method ellenőrzés
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Érvénytelen kérés');
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
    ApiResponse::validationError(['name' => 'A név megadása kötelező']);
}

if (!$client_id) {
    ApiResponse::validationError(['id' => 'Hiányzó ügyfél ID']);
}

try {
    // Ellenőrizzük hogy az ügyfél létezik és elutasított státuszú
    $client = $clientRepo->getClientById($client_id);

    if (!$client) {
        ApiResponse::notFound('Az ügyfél nem található');
    }

    // Csak a saját elutasított ügyfelét küldheti újra
    if ($client['created_by'] != $_SESSION['user_id']) {
        ApiResponse::unauthorized('Csak a saját ügyfeleidet küldheted újra');
    }

    if ($client['approval_status'] !== 'rejected') {
        ApiResponse::error('Csak elutasított ügyfelet küldhetsz újra', null, 400);
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
        'approved' => 0,
        'approved_at' => null,
        'approved_by' => null,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Frissítés a repository-val
    $clientRepo->updateClient($client_id, $fields);

    // Cache invalidálás - ügyfél státusza változott (approved/rejected -> pending)
    cache_delete('counties_with_counts');

    ApiResponse::success(null, 'Ügyfél sikeresen újraküldve jóváhagyásra!');

} catch (PDOException $e) {
    ApiResponse::error(
        'Adatbázis hiba történt',
        'Database error in resubmit_client: ' . $e->getMessage(),
        500,
        ['client_id' => $client_id, 'user_id' => $_SESSION['user_id']]
    );
} catch (Exception $e) {
    ApiResponse::error(
        'Váratlan hiba történt',
        'Unexpected error in resubmit_client: ' . $e->getMessage(),
        500
    );
}
