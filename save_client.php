<?php
require_once 'config.php';
ApiRoute::protect('auth');

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
    // Instantiate repositories
    $clientRepo = new ClientRepository($pdo);
    $agentRepo = new AgentRepository($pdo);

    // Lekérdezzük az ügyfelet
    $currentClient = $clientRepo->getClientByIdWithSettlement((int) $client_id);
    if (!$currentClient) {
        ApiResponse::notFound('Az ügyfél nem található');
    }

    // Jogosultság ellenőrzése
    $current_user_agent_id = null;
    if (!isAdmin()) {
        $current_user_agent_id = $agentRepo->getAgentIdByName($_SESSION['name']);
        // Ügyintéző (nem admin) csak akkor módosíthat, ha nincs ügyintéző rendelve vagy ő van hozzárendelve
        $can_edit = ($current_user_agent_id && ($currentClient['agent_id'] === null || $currentClient['agent_id'] == $current_user_agent_id));
        if (!$can_edit) {
            ApiResponse::unauthorized('Nincs jogosultságod ehhez az ügyfélhez');
        }
    }

    // Automatikus lezárás/újranyitás logika (adminoknak és ügyintézőknek is)
    $closed_at = null;
    $contract_signed_at = null;

    // Ellenőrizzük a jelenlegi állapotot
    $existing = [
        'closed_at' => $currentClient['closed_at'],
        'contract_signed' => $currentClient['contract_signed'],
        'contract_signed_at' => $currentClient['contract_signed_at'],
    ];

    // Szerződés aláírás dátumának kezelése
    if ($contract_signed) {
        if ($existing['contract_signed_at']) {
            // Már van szerződés dátum, megtartjuk
            $contract_signed_at = $existing['contract_signed_at'];
        } elseif (!$existing['contract_signed']) {
            // Most lett bepipálva először, új dátumot adunk
            $contract_signed_at = date('Y-m-d H:i:s');
        }
    } else {
        // ✅ MEGTARTJUK a régi dátumot még ha le is van pipálva
        $contract_signed_at = $existing['contract_signed_at'];
    }

    // Ha mindkét pipa bejelölve, lezárjuk
    if ($contract_signed && $work_completed) {
        if ($existing['closed_at']) {
            $closed_at = $existing['closed_at'];
        } else {
            $closed_at = date('Y-m-d H:i:s');
        }
    } else {
        // ✅ MEGTARTJUK a régi closed_at dátumot
        $closed_at = $existing['closed_at'];
    }

    // Üres agent_id vagy insulation_area kezelése
    $agent_id = !empty($agent_id) ? $agent_id : null;
    $insulation_area = !empty($insulation_area) ? $insulation_area : null;

    // Mezők összeállítása frissítéshez
    $fields = [];
    if (isAdmin()) {
        // Admin frissítheti az összes mezőt
        $fields = [
            'name' => $name,
            'county_id' => $county_id,
            'settlement_id' => $settlement_id,
            'address' => $address,
            'email' => $email,
            'phone' => $phone,
            'insulation_area' => $insulation_area,
            'contract_signed' => $contract_signed,
            'work_completed' => $work_completed,
            'agent_id' => $agent_id,
            'notes' => $notes,
            'closed_at' => $closed_at,
            'contract_signed_at' => $contract_signed_at,
        ];
    } else {
        // Ügyintézők csak bizonyos mezőket módosíthatnak
        $allowed_agent_id = null;
        if ($agent_id == $current_user_agent_id || empty($agent_id)) {
            $allowed_agent_id = $agent_id;
        } else {
            $allowed_agent_id = $currentClient['agent_id'];
        }
        $fields = [
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'notes' => $notes,
            'insulation_area' => $insulation_area,
            'agent_id' => $allowed_agent_id,
            'contract_signed' => $contract_signed,
            'work_completed' => $work_completed,
            'closed_at' => $closed_at,
            'contract_signed_at' => $contract_signed_at,
        ];
    }


    // Frissítés repository segítségével
    $clientRepo->updateClient((int) $client_id, $fields);

    // Cache invalidálás - a megyék ügyfélszámai változhattak
    cache_delete('counties_with_counts');

    // Sikeres válasz
    ApiResponse::success(null, 'Ügyfél sikeresen frissítve');

} catch (PDOException $e) {
    // Adatbázis hiba - ne add vissza a részleteket!
    ApiResponse::error(
        'Adatbázis hiba történt',
        'Database error in client update: ' . $e->getMessage(),
        500,
        ['client_id' => $client_id, 'user_id' => $_SESSION['user_id']]
    );
} catch (Exception $e) {
    // Általános hiba
    ApiResponse::error(
        'Váratlan hiba történt',
        'Unexpected error in save_client: ' . $e->getMessage(),
        500
    );
}
