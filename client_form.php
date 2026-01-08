<?php
require_once 'config.php';
require_once 'audit_helper.php';
Route::protect('auth');

// ============================================================================
// HELPER FUNCTIONS - Client form operations
// ============================================================================

/**
 * Update client with contract date handling
 * Preserves existing dates when checkboxes are unchecked
 */
function updateClientWithDates($clientRepo, $client_id, $fields, $contract_signed, $work_completed) {
    $existing = $clientRepo->getClientById($client_id);
    if (!$existing) {
        return ['success' => false, 'message' => 'Ügyfél nem található!'];
    }
    
    // Preserve existing dates by default
    $contract_signed_at = $existing['contract_signed_at'];
    $closed_at = $existing['closed_at'];
    
    // Contract signed date handling
    if ($contract_signed && !$existing['contract_signed_at']) {
        $contract_signed_at = date('Y-m-d H:i:s');
    }
    
    // Closed date handling
    if ($contract_signed && $work_completed && !$existing['closed_at']) {
        $closed_at = date('Y-m-d H:i:s');
    }
    
    // Merge date fields with provided fields
    $updateFields = array_merge($fields, [
        'contract_signed' => $contract_signed,
        'work_completed' => $work_completed,
        'contract_signed_at' => $contract_signed_at,
        'closed_at' => $closed_at
    ]);
    
    $clientRepo->updateClient($client_id, $updateFields);
    return ['success' => true, 'message' => 'Ügyfél sikeresen frissítve!'];
}

/**
 * Check if an agent can edit a specific client
 * Returns true if client has no agent or is assigned to current agent
 */
function canAgentEditClient($client_agent_id, $current_agent_id) {
    if ($client_agent_id === null) return true; // No agent assigned
    if ($current_agent_id && $client_agent_id == $current_agent_id) return true; // Current agent
    return false; // Assigned to someone else
}

// ============================================================================
// END HELPER FUNCTIONS
// ============================================================================

// Instantiate repositories
$countyRepo     = new CountyRepository($pdo);
$settlementRepo = new SettlementRepository($pdo);
$agentRepo      = new AgentRepository($pdo);
$clientRepo     = new ClientRepository($pdo);

$county_id = $_GET['county_id'] ?? 0;
$client_id = $_GET['id'] ?? 0;
$is_edit   = $client_id > 0;

// Megye lekérdezése repository segítségével
$county = null;
if ($county_id) {
    $county = $countyRepo->getCountyById((int)$county_id);
}

// Ha szerkesztés, ügyfél adatok lekérdezése repository segítségével
$client = null;
if ($is_edit) {
    $client = $clientRepo->getClientByIdWithSettlement((int)$client_id);
    if (!$client) {
        ErrorHandler::logAppError('Client not found in edit mode', [
            'client_id' => $client_id,
            'user_id' => $_SESSION['user_id']
        ]);
        $_SESSION['error'] = 'Az ügyfél nem található';
        redirect('index.php');
    }
    $county_id = $client['county_id'];
    $county    = $countyRepo->getCountyById((int)$county_id);
}

if (!$county) {
    ErrorHandler::logAppError('County not found in client form', [
        'county_id' => $county_id,
        'user_id' => $_SESSION['user_id'],
        'is_edit' => $is_edit
    ]);
    $_SESSION['error'] = 'A megye nem található';
    redirect('index.php');
}

// Összes megye lekérdezése
$counties = $countyRepo->getAll();

// Települések lekérdezése a kiválasztott megyéhez
$settlements = $settlementRepo->getSettlementsByCounty((int)$county_id);

// Ügyintézők lekérdezése (csak aktív felhasználók)
$agents = $agentRepo->getActiveAgents();

// Ha az aktuális felhasználó még nincs az agents táblában, hozzáadjuk
$current_user_in_agents = false;
foreach ($agents as $agent) {
    if ($agent['name'] === $_SESSION['name']) {
        $current_user_in_agents = true;
        break;
    }
}

if (!$current_user_in_agents) {
    // Hozzáadjuk az aktuális felhasználót az agents listához (csak megjelenítéshez)
    $agents[] = [
        'id' => 'current_user',
        'name' => $_SESSION['name'],
        'color' => '#808080' // Szürke szín az új felhasználóknak
    ];
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $selected_county_id = $_POST['county_id'] ?? $county_id;
    $settlement_id = !empty($_POST['settlement_id']) ? $_POST['settlement_id'] : null;
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $insulation_area = !empty($_POST['insulation_area']) ? $_POST['insulation_area'] : null;
    $contract_signed = isset($_POST['contract_signed']) ? 1 : 0;
    $work_completed = isset($_POST['work_completed']) ? 1 : 0;
    $agent_id_raw = $_POST['agent_id'] ?? '';

    // Ha az ügyintéző 'current_user', akkor hozzáadjuk az agents táblához
    if ($agent_id_raw === 'current_user') {
        // Ellenőrizzük, hogy már létezik-e (repository használatával)
        $agent_id = $agentRepo->getAgentIdByName($_SESSION['name']);
        
        if ($agent_id === null) {
            // Hozzáadjuk az új ügyintézőt (repository használatával)
            $agent_id = $agentRepo->createAgent($_SESSION['name'], '#808080');
        }
    } else {
        $agent_id = !empty($agent_id_raw) ? $agent_id_raw : null;
    }
    $notes = trim($_POST['notes'] ?? '');

    // Ügyintéző szerkesztés - ellenőrizzük a védettséget
    if (!isAdmin() && $is_edit) {
        // Ellenőrizzük, hogy az ügyfélhez már van-e ügyintéző rendelve (repository használatával)
        $current_client = $clientRepo->getClientById($client_id);

        // Ha már van ügyintéző és nem az aktuális felhasználó, nem szerkeszthető
        if ($current_client['agent_id'] && $current_client['agent_id'] != $agent_id) {
            // Check if current user is the assigned agent
            $current_agent_id = $agentRepo->getAgentIdByName($_SESSION['name']);

            if (!canAgentEditClient($current_client['agent_id'], $current_agent_id)) {
                $error = 'Ez az ügyfél már egy másik ügyintézőhöz van rendelve. Nem szerkesztheted!';
            } else {
                // Update client with date handling using helper function
                $result = updateClientWithDates(
                    $clientRepo,
                    $client_id,
                    [
                        'agent_id' => $agent_id,
                        'phone' => $phone,
                        'address' => $address,
                        'insulation_area' => $insulation_area,
                        'notes' => $notes
                    ],
                    $contract_signed,
                    $work_completed
                );
                
                if ($result['success']) {
                    $success = $result['message'];
                    header("refresh:3;url=county.php?id=$county_id");
                } else {
                    $error = $result['message'];
                }
            }
        } else {
            // No agent assigned or current user is the agent - can edit
            $result = updateClientWithDates(
                $clientRepo,
                $client_id,
                [
                    'agent_id' => $agent_id,
                    'phone' => $phone,
                    'address' => $address,
                    'insulation_area' => $insulation_area,
                    'notes' => $notes
                ],
                $contract_signed,
                $work_completed
            );
            
            if ($result['success']) {
                $success = $result['message'];
                header("refresh:3;url=county.php?id=$county_id");
            } else {
                $error = $result['message'];
            }
        }
    } else {
        // Validació: Kötelező mezők ellenőrzése (csak adminoknak és új ügyfélnél)
        if (empty($name)) {
            $error = 'A név megadása kötelező!';
        } elseif (empty($selected_county_id)) {
            $error = 'A megye kiválasztása kötelező!';
        } elseif (empty($settlement_id)) {
            $error = 'A település kiválasztása kötelező!';
        } elseif (empty($phone)) {
            $error = 'A telefonszám megadása kötelező!';
        } else {
            // E-mail duplikáció ellenőrzés (csak ha van e-mail cím)
            if (!empty($email)) {
                // E-mail formátum ellenőrzés
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Az e-mail cím formátuma helytelen!';
                } else {
                    // Duplikáció ellenőrzés
                    if ($is_edit) {
                        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $client_id]);
                    } else {
                        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
                        $stmt->execute([$email]);
                    }

                    if ($stmt->fetch()) {
                        $error = 'Ez az e-mail cím már használatban van egy másik ügyfélnél!';
                    }
                }
            }
        }

        if (!$error) {
            // Admin és Ügyintéző hozzáadás/szerkesztés
            if ($is_edit) {
                // Szerkesztésnél
                if (isAdmin()) {
                    // Régi adatok lekérése audit loghoz
                    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
                    $stmt->execute([$client_id]);
                    $old_data = $stmt->fetch();

                    // Automatikus lezárás/újranyitás logika
                    // ✅ Lekérjük a meglévő contract_signed_at és closed_at értékeket
                    $check_stmt = $pdo->prepare("SELECT contract_signed_at, closed_at FROM clients WHERE id = ?");
                    $check_stmt->execute([$client_id]);
                    $existing = $check_stmt->fetch();
                    
                    $contract_signed_at = $existing['contract_signed_at']; // Alapból megtartjuk
                    $closed_at = $existing['closed_at']; // Alapból megtartjuk
                    
                    // contract_signed_at kezelése
                    if ($contract_signed) {
                        // Ha nincs még dátum és most jelöltük be, új dátumot adunk
                        if (!$existing['contract_signed_at']) {
                            $contract_signed_at = date('Y-m-d H:i:s');
                        }
                    }
                    // Már van contract_signed_at de ki van kapcsolva? MEGTARTJUK!
                    
                    // closed_at kezelése
                    if ($contract_signed && $work_completed) {
                        // Ha nincs még closed_at, most állítjuk be
                        if (!$existing['closed_at']) {
                            $closed_at = date('Y-m-d H:i:s');
                        }
                    }
                    // Már van closed_at de bármelyik ki van kapcsolva? MEGTARTJUK!

                    // Admin minden mezőt módosíthat
                    $stmt = $pdo->prepare("
                        UPDATE clients SET
                            name = ?, county_id = ?, settlement_id = ?, address = ?,
                            email = ?, phone = ?, insulation_area = ?,
                            contract_signed = ?, work_completed = ?, agent_id = ?, notes = ?,
                            contract_signed_at = ?, closed_at = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $selected_county_id, $settlement_id, $address,
                        $email, $phone, $insulation_area,
                        $contract_signed, $work_completed, $agent_id, $notes, $contract_signed_at, $closed_at, $client_id
                    ]);

                    // AUDIT LOG - Frissítés
                    $new_data = [
                        'name' => $name,
                        'county_id' => $selected_county_id,
                        'settlement_id' => $settlement_id,
                        'address' => $address,
                        'email' => $email,
                        'phone' => $phone,
                        'insulation_area' => $insulation_area,
                        'contract_signed' => $contract_signed,
                        'work_completed' => $work_completed,
                        'agent_id' => $agent_id,
                        'notes' => $notes,
                        'closed_at' => $closed_at
                    ];
                    logClientUpdate($pdo, $client_id, $old_data, $new_data);

                    $success = 'Ügyfél sikeresen frissítve!';
                }

            } else {
                // Új ügyfél hozzáadása
                // JAVÍTÁS: contract_signed_at és closed_at számítása új ügyféleknél
                $contract_signed_at = null;
                $closed_at = null;
                
                if ($contract_signed) {
                    $contract_signed_at = date('Y-m-d H:i:s');
                }
                if ($contract_signed && $work_completed) {
                    $closed_at = date('Y-m-d H:i:s');
                }
                
                // Új ügyfél hozzáadása
                if (isAdmin()) {
                    // Admin által létrehozott ügyfél azonnal jóváhagyott
                    $stmt = $pdo->prepare("
                        INSERT INTO clients
                        (name, county_id, settlement_id, address, email, phone, insulation_area,
                        contract_signed, work_completed, agent_id, notes, created_by, approval_status, contract_signed_at, closed_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?)
                    ");
                    $stmt->execute([
                        $name, $selected_county_id, $settlement_id, $address, $email, $phone, $insulation_area,
                        $contract_signed, $work_completed, $agent_id, $notes, $_SESSION['user_id'], $contract_signed_at, $closed_at
                    ]);
    
                    $new_client_id = $pdo->lastInsertId();
    
                    // AUDIT LOG - Új ügyfél létrehozása
                    logClientCreate($pdo, $new_client_id, [
                        'name' => $name,
                        'county_id' => $selected_county_id,
                        'settlement_id' => $settlement_id,
                        'address' => $address,
                        'email' => $email,
                        'phone' => $phone,
                        'insulation_area' => $insulation_area,
                        'contract_signed' => $contract_signed,
                        'work_completed' => $work_completed,
                        'agent_id' => $agent_id,
                        'notes' => $notes,
                        'created_by' => $_SESSION['user_id'],
                        'approval_status' => 'approved'
                    ]);
    
                    // Cache invalidálás - új jóváhagyott ügyfél hozzáadva
                    cache_delete('counties_with_counts');
    
                    $success = 'Ügyfél sikeresen létrehozva!';
                } else {
                    // Ügyintéző által létrehozott ügyfél jóváhagyásra vár
                    $stmt = $pdo->prepare("
                        INSERT INTO clients
                        (name, county_id, settlement_id, address, email, phone, insulation_area,
                        contract_signed, work_completed, agent_id, notes, created_by, approval_status, contract_signed_at, closed_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
                    ");
                    $stmt->execute([
                        $name, $selected_county_id, $settlement_id, $address, $email, $phone, $insulation_area,
                        $contract_signed, $work_completed, $agent_id, $notes, $_SESSION['user_id'], $contract_signed_at, $closed_at
                    ]);
    
                    $new_client_id = $pdo->lastInsertId();
    
                    // AUDIT LOG - Új ügyfél (pending)
                    logClientCreate($pdo, $new_client_id, [
                        'name' => $name,
                        'county_id' => $selected_county_id,
                        'settlement_id' => $settlement_id,
                        'created_by' => $_SESSION['user_id'],
                        'approval_status' => 'pending'
                    ]);
    
                    // Cache invalidálás (bár pending ügyfelek nem számítanak, de konzisztencia miatt)
                    cache_delete('counties_with_counts');
    
                    $success = 'Ügyfél sikeresen létrehozva! Jóváhagyásra vár az adminisztrátor által.';
                }

            }

            // Átirányítás 1 másodperc után (csökkentve 3-ról, gyorsabb workflow)
            header("refresh:3;url=county.php?id=$selected_county_id");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo $is_edit ? 'Ügyfél szerkesztése' : 'Új ügyfél'; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/crm-complete.css?v=<?php echo APP_VERSION; ?>">

    <script src="assets/js/error-logger.js"></script>
    <script src="assets/js/crm-main.js?v=<?php echo APP_VERSION; ?>"></script>
</head>
<body class="page-client-form">
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><?php echo APP_NAME; ?></h3>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <?php if (isAdmin()): ?>
                            <span class="badge bg-primary ms-2">Admin</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="mb-4">
            <a href="county.php?id=<?php echo $county_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Vissza
            </a>
        </div>

        <div class="form-card p-4">
            <h4 class="mb-4">
                <?php echo $is_edit ? 'Ügyfél szerkesztése' : 'Új ügyfél hozzáadása - ' . escape($county['name']); ?>
            </h4>

            <?php if (!isAdmin() && $is_edit): ?>
                <div class="alert alert-info">
                    Ügyintézőként csak az ügyintéző, szerződéskötés és kivitelezés mezőket módosíthatod.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo escape($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo escape($success); ?></div>
            <?php endif; ?>

            <form method="POST" id="clientForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Név *</label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?php echo escape($client['name'] ?? ''); ?>"
                               <?php echo (!isAdmin() && $is_edit) ? 'readonly' : 'required'; ?>>
                    </div>

                    <?php if (isAdmin() || !$is_edit): ?>
                        <div class="col-md-6">
                            <label for="county_id" class="form-label">Megye *</label>
                            <select class="form-select" id="county_id" name="county_id" required
                                    onchange="loadSettlements(this.value)">
                                <?php foreach ($counties as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"
                                            <?php echo ($c['id'] == $county_id) ? 'selected' : ''; ?>>
                                        <?php echo escape($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="settlement_search" class="form-label">Település *</label>
                            <input type="text" class="form-control" id="settlement_search"
                                   placeholder="Írj be egy települést..."
                                   autocomplete="off"
                                   required
                                   value="<?php echo $client ? escape($client['settlement_name'] ?? '') : ''; ?>">
                            <input type="hidden" id="settlement_id" name="settlement_id"
                                   value="<?php echo $client['settlement_id'] ?? ''; ?>"
                                   required>
                            <div id="settlement_dropdown" class="list-group position-absolute dropdown-autocomplete"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo escape($client['email'] ?? ''); ?>">
                        </div>
                    <?php endif; ?>

                    <!-- Ezeket a mezőket mindenki szerkesztheti -->
                    <div class="col-md-6">
                        <label for="address" class="form-label">Utca/Házszám</label>
                        <input type="text" class="form-control" id="address" name="address"
                               value="<?php echo escape($client['address'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Telefon *</label>
                        <input type="text" class="form-control" id="phone" name="phone"
                               required
                               value="<?php echo escape($client['phone'] ?? ''); ?>"
                               placeholder="+36 XX XXX XXXX">
                    </div>

                    <div class="col-md-6">
                        <label for="insulation_area" class="form-label">Szigetelenő terület (m²)</label>
                        <input type="number" class="form-control" id="insulation_area" name="insulation_area"
                               value="<?php echo escape($client['insulation_area'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="agent_id" class="form-label">Ügyintéző</label>
                        <select class="form-select" id="agent_id" name="agent_id">
                            <option value="">Válassz ügyintézőt</option>
                            <?php
                            // Ha ügyintéző, csak saját magát láthatja
                            if (!isAdmin()) {
                                // Csak az aktuális felhasználó megtalálása
                                foreach ($agents as $a) {
                                    if ($a['name'] === $_SESSION['name'] || $a['id'] === 'current_user') {
                                        $selected = ($client && $a['id'] == $client['agent_id']) ? 'selected' : '';
                                        echo '<option value="' . $a['id'] . '" ' . $selected . '>' . escape($a['name']) . '</option>';
                                        break;
                                    }
                                }
                            } else {
                                // Admin látja az összeset
                                foreach ($agents as $a) {
                                    $selected = ($client && $a['id'] == $client['agent_id']) ? 'selected' : '';
                                    echo '<option value="' . $a['id'] . '" ' . $selected . '>' . escape($a['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="contract_signed" name="contract_signed"
                                   <?php echo ($client && $client['contract_signed']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="contract_signed">
                                Szerződéskötés?
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="work_completed" name="work_completed"
                                   <?php echo ($client && $client['work_completed']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="work_completed">
                                Kivitelezés?
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Megjegyzés</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo escape($client['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Mentés
                    </button>
                    <a href="county.php?id=<?php echo $county_id; ?>" class="btn btn-outline-secondary">
                        Mégse
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let settlements = [];
        let currentCountyId = <?php echo $county_id; ?>;

        // Települések betöltése a megyéhez (Promise-szal)
        function loadSettlements(countyId, preserveSelection = false) {
            currentCountyId = countyId;
            return fetch('api_settlements.php?county_id=' + countyId)
                .then(response => response.json())
                .then(data => {
                    settlements = data;
                    if (!preserveSelection) {
                        document.getElementById('settlement_search').value = '';
                        document.getElementById('settlement_id').value = '';
                    }
                    return data;
                });
        }

        // Autocomplete funkció
        const searchInput = document.getElementById('settlement_search');
        const dropdown = document.getElementById('settlement_dropdown');
        const hiddenInput = document.getElementById('settlement_id');

        // Települések betöltése az oldal betöltésekor
        <?php if ($is_edit && $client && !empty($client['settlement_name'])): ?>
        // Szerkesztés mód: betöltjük a településeket, majd beállítjuk a meglévőt
        loadSettlements(currentCountyId, true).then(function() {
            const settlementName = <?php echo json_encode($client['settlement_name'] ?? ''); ?>;
            const settlementId = <?php echo json_encode($client['settlement_id'] ?? ''); ?>;
            document.getElementById('settlement_search').value = settlementName;
            document.getElementById('settlement_id').value = settlementId;
        });
        <?php else: ?>
        // Új ügyfél mód: csak betöltjük a településeket
        loadSettlements(currentCountyId);
        <?php endif; ?>

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();

            if (searchTerm.length === 0) {
                dropdown.style.display = 'none';
                hiddenInput.value = '';
                return;
            }

            const filtered = settlements.filter(s =>
                s.name.toLowerCase().includes(searchTerm)
            );

            if (filtered.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            dropdown.innerHTML = '';
            filtered.forEach(settlement => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = settlement.name;
                item.onclick = function(e) {
                    e.preventDefault();
                    searchInput.value = settlement.name;
                    hiddenInput.value = settlement.id;
                    dropdown.style.display = 'none';
                };
                dropdown.appendChild(item);
            });

            dropdown.style.display = 'block';
        });

        // Kattintás máshova -> bezárás
        document.addEventListener('click', function(e) {
            if (e.target !== searchInput) {
                dropdown.style.display = 'none';
            }
        });
        
        // Telefonszám formázás
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            attachPhoneFormatter(phoneInput);
        }

        // Megye változásakor frissítjük a településeket
        const countySelect = document.getElementById('county_id');
        if (countySelect) {
            countySelect.addEventListener('change', function() {
                loadSettlements(this.value);
            });
        }
    </script>
</body>
</html>


