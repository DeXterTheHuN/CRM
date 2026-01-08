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

// Település kötelező validáció
if (empty($settlement_id) || $settlement_id <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Validációs hiba',
        'errors' => ['settlement_id' => 'A település megadása kötelező']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Telefonszám formátum ellenőrzése
// Formátum: +36 XX XXX XXXX
if (!empty($phone)) {
    $phone_pattern = '/^\+36 [0-9]{2} [0-9]{3} [0-9]{4}$/';
    if (!preg_match($phone_pattern, $phone)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'Validációs hiba',
            'errors' => ['phone' => 'Helytelen telefonszám formátum! Helyes: +36 XX XXX XXXX (pl. +36 70 228 6530)']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
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
    // Instantiate repositories
    $clientRepo = new ClientRepository($pdo);
    $agentRepo = new AgentRepository($pdo);

    // Lekérdezzük az ügyfelet
    $currentClient = $clientRepo->getClientByIdWithSettlement((int) $client_id);
    if (!$currentClient) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Az ügyfél nem található'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Jogosultság ellenőrzése
    $current_user_agent_id = null;
    if (!isAdmin()) {
        $current_user_agent_id = $agentRepo->getAgentIdByName($_SESSION['name']);
        // Ügyintéző (nem admin) csak akkor módosíthat, ha nincs ügyintéző rendelve vagy ő van hozzárendelve
        $can_edit = ($current_user_agent_id && ($currentClient['agent_id'] === null || $currentClient['agent_id'] == $current_user_agent_id));
        if (!$can_edit) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Nincs jogosultságod ehhez az ügyfélhez'], JSON_UNESCAPED_UNICODE);
            exit;
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
        } else {
            // NINCS még dátum, új dátumot adunk (első bepipálás vagy korábban NULL volt)
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

    // Settlement ID kezelése: biztonságosan NULL-ra állítjuk ha üres
    $settlement_id = !empty($settlement_id) ? (int) $settlement_id : null;

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

    // Cache invalidálás ELTÁVOLÍTVA - a megyék ügyfélszámai cache-elve maradnak
    // Ez jelentősen gyorsítja a mentés utáni átirányítást
    // A cache automatikusan frissül CACHE_TTL_SHORT (5 perc) után
    // cache_delete('counties_with_counts');

    // Sikeres válasz
    echo json_encode(['success' => true, 'message' => 'Ügyfél sikeresen frissítve'], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Adatbázis hiba - részletes logging
    $errorDetails = [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'client_id' => $client_id,
        'fields' => $fields ?? []
    ];
    error_log('Database error in client update: ' . json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Adatbázis hiba történt: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // Általános hiba
    error_log('Unexpected error in save_client: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Váratlan hiba történt'], JSON_UNESCAPED_UNICODE);
}
