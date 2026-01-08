<?php
require_once 'config.php';
require_once 'audit_helper.php';
Route::protect('admin');

$success = '';
$error = '';

// Ügyfél jóváhagyása
if (isset($_POST['approve_client'])) {
    $client_id = (int) $_POST['client_id'];

    // Ügyfél adatok lekérdezése értesítéshez ÉS audit loghoz (repository használatával)
    $clientRepo = new ClientRepository($pdo);
    $client = $clientRepo->getClientById($client_id);

    if (!$client) {
        $error = 'Az ügyfél nem található!';
    } else {
        // Jóváhagyás (repository használatával)
        $clientRepo->approveClient($client_id, $_SESSION['user_id']);

        // AUDIT LOG - Jóváhagyás
        logClientApprove($pdo, $client_id, [
            'name' => $client['name'],
            'county_id' => $client['county_id'],
            'settlement_id' => $client['settlement_id'],
            'created_by' => $client['created_by'],
            'approved_by' => $_SESSION['user_id']
        ]);

        // Értesítés létrehozása az ügyintézőnek (repository használatával)
        if ($client && $client['created_by']) {
            $notificationRepo = new ApprovalNotificationRepository($pdo);
            $notificationRepo->createNotification(
                $client_id,
                $client['created_by'],
                $client['name'],
                'approved'
            );
        }

        // Cache invalidálás - jóváhagyott ügyfél hozzáadva a megyéhez
        cache_delete('counties_with_counts');

        $success = 'Ügyfél sikeresen jóváhagyva!';
    }
}

// Ügyfél elutasítása indoklással
if (isset($_POST['reject_client'])) {
    $client_id = (int) $_POST['client_id'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    if (empty($rejection_reason)) {
        $error = 'Az elutasítás indoklása kötelező!';
    } else {
        // Ügyfél adatok lekérdezése értesítéshez ÉS audit loghoz (repository használatával)
        if (!isset($clientRepo)) {
            $clientRepo = new ClientRepository($pdo);
        }
        $client = $clientRepo->getClientById($client_id);

        if (!$client) {
            $error = 'Az ügyfél nem található!';
        } else {
            // Elutasítás (repository használatával)
            $clientRepo->rejectClient($client_id, $_SESSION['user_id'], $rejection_reason);

            // AUDIT LOG - Elutasítás
            logClientReject($pdo, $client_id, [
                'name' => $client['name'],
                'county_id' => $client['county_id'],
                'settlement_id' => $client['settlement_id'],
                'created_by' => $client['created_by']
            ], $rejection_reason);

            // Értesítés létrehozása az ügyintézőnek (repository használatával)
            if ($client && $client['created_by']) {
                if (!isset($notificationRepo)) {
                    $notificationRepo = new ApprovalNotificationRepository($pdo);
                }
                $notificationRepo->createNotification(
                    $client_id,
                    $client['created_by'],
                    $client['name'],
                    'rejected',
                    $rejection_reason
                );
            }

            $success = 'Ügyfél elutasítva!';
        }
    }
}

// A többi rész változatlan...


// Jóváhagyásra váró ügyfelek lekérdezése (repository használatával)
$clientRepo = new ClientRepository($pdo);
$pending_clients = $clientRepo->getPendingClients();

// Települések AJAX-szal lesznek betöltve (nem terheljük a DOM-ot 3000+ option-nel)

// Ügyintézők lekérdezése (repository használatával)
$agentRepo = new AgentRepository($pdo);
$agents = $agentRepo->getAll();

// Megyék lekérdezése (repository használatával)
$countyRepo = new CountyRepository($pdo);
$counties = $countyRepo->getAll();
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Jóváhagyások - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/crm-complete.css?v=<?php echo APP_VERSION; ?>">

    <script src="assets/js/error-logger.js"></script>
    <script src="assets/js/crm-main.js?v=<?php echo APP_VERSION; ?>"></script>
</head>

<body class="page-approvals">
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><?php echo APP_NAME; ?></h3>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <span class="badge bg-primary">Admin</span>
                    </span>
                    <a href="index.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-house"></i> Főoldal
                    </a>
                    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-people"></i> Felhasználók
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Kijelentkezés
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="mb-4">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Vissza a főoldalra
            </a>
        </div>

        <div class="approval-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <i class="bi bi-clock-history"></i> Jóváhagyásra Váró Ügyfelek
                </h4>
                <span class="badge bg-warning text-dark fs-6">
                    <?php echo count($pending_clients); ?> várakozik
                </span>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo escape($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo escape($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($pending_clients)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Jelenleg nincsenek jóváhagyásra váró ügyfelek.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Név</th>
                                <th>Megye</th>
                                <th>Település</th>
                                <th>Telefon</th>
                                <th>E-mail</th>
                                <th>Ügyintéző</th>
                                <th>Létrehozta</th>
                                <th>Dátum</th>
                                <th class="col-actions">Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_clients as $client): ?>
                                <tr>
                                    <td><?php echo $client['id']; ?></td>
                                    <td>
                                        <strong><?php echo escape($client['name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo escape($client['address'] ?? '-'); ?>
                                        </small>
                                    </td>
                                    <td><?php echo escape($client['county_name']); ?></td>
                                    <td><?php echo escape($client['settlement_name']); ?></td>
                                    <td><?php echo escape($client['phone']); ?></td>
                                    <td><?php echo escape($client['email']); ?></td>
                                    <td>
                                        <?php if ($client['agent_name']): ?>
                                            <span class="badge bg-secondary"><?php echo escape($client['agent_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo escape($client['creator_name']); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('Y-m-d H:i', strtotime($client['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-info"
                                            onclick="editClient(<?php echo htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8'); ?>)"
                                            title="Szerkesztés">
                                            <i class="bi bi-pencil"></i> Szerkeszt
                                        </button>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                            <button type="submit" name="approve_client" class="btn btn-sm btn-success"
                                                title="Jóváhagyás">
                                                <i class="bi bi-check-circle"></i> Jóváhagy
                                            </button>
                                        </form>
                                        <button class="btn btn-sm btn-danger"
                                            onclick="showRejectModal(<?php echo $client['id']; ?>, '<?php echo escape($client['name']); ?>')"
                                            title="Elutasítás">
                                            <i class="bi bi-x-circle"></i> Elutasít
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-4">
            <div class="alert alert-info">
                <strong><i class="bi bi-info-circle"></i> Tudnivalók:</strong>
                <ul class="mb-0 mt-2">
                    <li>Az ügyintézők által létrehozott ügyfelek automatikusan jóváhagyásra várnak</li>
                    <li>Szerkesztheted az ügyfél adatait jóváhagyás előtt</li>
                    <li>A jóváhagyott ügyfelek megjelennek a megyei listákban</li>
                    <li>Az elutasított ügyfeleket kötelező megindokolni - az ügyintéző látja az indoklást</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Elutasítás Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-x-circle"></i> Ügyfél Elutasítása
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="client_id" id="reject_client_id">
                        <p>Biztosan elutasítod ezt az ügyfelet: <strong id="reject_client_name"></strong>?</p>
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">
                                <strong>Elutasítás indoklása *</strong>
                            </label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4"
                                required
                                placeholder="Pl: Hibás telefonszám, nem valós cím, duplikált ügyfél, stb."></textarea>
                            <small class="text-muted">Az ügyintéző látni fogja ezt az indoklást.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x"></i> Mégse
                        </button>
                        <button type="submit" name="reject_client" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> Elutasít
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Szerkesztés Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil"></i> Ügyfél Szerkesztése
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="edit_client_id">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>Név *</strong></label>
                                <input type="text" class="form-control" id="edit_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>Telefon</strong></label>
                                <input type="text" class="form-control" id="edit_phone" placeholder="+36 XX XXX XXXX">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>E-mail</strong></label>
                                <input type="email" class="form-control" id="edit_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>Cím</strong></label>
                                <input type="text" class="form-control" id="edit_address">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>Megye *</strong></label>
                                <select class="form-select" id="edit_county_id" required>
                                    <?php foreach ($counties as $county): ?>
                                        <option value="<?php echo $county['id']; ?>">
                                            <?php echo escape($county['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>Település *</strong></label>
                                <input type="text" class="form-control" id="edit_settlement_search"
                                    placeholder="Kezdj el gépelni..." autocomplete="off" required>
                                <input type="hidden" id="edit_settlement_id" required>
                                <div id="edit_settlement_dropdown" class="list-group position-absolute"
                                    style="display: none; max-height: 300px; overflow-y: auto; z-index: 1000; width: auto;">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>Ügyintéző</strong></label>
                                <select class="form-select" id="edit_agent_id">
                                    <option value="">- Nincs -</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?php echo $agent['id']; ?>">
                                            <?php echo escape($agent['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>Szigetelendő terület (m²)</strong></label>
                                <input type="number" class="form-control" id="edit_insulation_area">
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input form-check-lg"
                                        id="edit_contract_signed">
                                    <label class="form-check-label ms-2" for="edit_contract_signed">
                                        Szerződés aláírva
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" id="edit_work_completed">
                                    <label class="form-check-label ms-2" for="edit_work_completed">
                                        Munka befejezve
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>Megjegyzések</strong></label>
                            <textarea class="form-control" id="edit_notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> Mégse
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveEdit()">
                        <i class="bi bi-save"></i> Mentés
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let rejectModal;
        let editModal;

        document.addEventListener('DOMContentLoaded', function () {
            rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
            editModal = new bootstrap.Modal(document.getElementById('editModal'));
        });

        function showRejectModal(clientId, clientName) {
            document.getElementById('reject_client_id').value = clientId;
            document.getElementById('reject_client_name').textContent = clientName;
            document.getElementById('rejection_reason').value = '';
            rejectModal.show();
        }

        function editClient(client) {
            // Mobile eszközön redirect a teljes szerkesztés oldalra (jobb UX)
            const isMobile = window.innerWidth < 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

            if (isMobile) {
                // Mobilon átirányítás a client_form.php-ra szerkesztéshez
                window.location.href = `client_form.php?id=${client.id}&from=approvals`;
                return;
            }

            // Desktop: modal megnyitás
            document.getElementById('edit_client_id').value = client.id;
            document.getElementById('edit_name').value = client.name || '';
            document.getElementById('edit_phone').value = client.phone || '';
            document.getElementById('edit_email').value = client.email || '';
            document.getElementById('edit_address').value = client.address || '';
            document.getElementById('edit_county_id').value = client.county_id || '';
            document.getElementById('edit_agent_id').value = client.agent_id || '';
            document.getElementById('edit_insulation_area').value = client.insulation_area || '';
            document.getElementById('edit_contract_signed').checked = client.contract_signed == 1;
            document.getElementById('edit_work_completed').checked = client.work_completed == 1;
            document.getElementById('edit_notes').value = client.notes || '';

            // Települések betöltése a megye alapján, majd settlement_id ÉS név beállítása
            if (client.county_id) {
                loadEditSettlements(client.county_id, client.settlement_id, client.settlement_name);
            }

            // Telefonszám formázás
            const phoneInput = document.getElementById('edit_phone');
            if (phoneInput) {
                attachPhoneFormatter(phoneInput);
            }

            editModal.show();
        }

        // Település betöltése AJAX-szal a szerkesztési modal-hoz
        let editSettlements = [];

        function loadEditSettlements(countyId, preserveSettlementId = null, preserveSettlementName = null) {
            if (!countyId) {
                editSettlements = [];
                document.getElementById('edit_settlement_search').value = '';
                document.getElementById('edit_settlement_id').value = '';
                document.getElementById('edit_settlement_search').placeholder = '- Először válassz megyét -';
                document.getElementById('edit_settlement_search').disabled = true;
                return;
            }

            document.getElementById('edit_settlement_search').placeholder = 'Betöltés...';
            document.getElementById('edit_settlement_search').disabled = true;

            fetch(`api_settlements.php?county_id=${countyId}`)
                .then(response => response.json())
                .then(data => {
                    editSettlements = Array.isArray(data) ? data : [];

                    // Ha van megtartandó település, beállítjuk
                    if (preserveSettlementId && preserveSettlementName) {
                        document.getElementById('edit_settlement_search').value = preserveSettlementName;
                        document.getElementById('edit_settlement_id').value = preserveSettlementId;
                    } else {
                        document.getElementById('edit_settlement_search').value = '';
                        document.getElementById('edit_settlement_id').value = '';
                    }

                    document.getElementById('edit_settlement_search').placeholder = 'Kezdj el gépelni...';
                    document.getElementById('edit_settlement_search').disabled = false;
                })
                .catch(error => {
                    console.error('Település betöltési hiba:', error);
                    editSettlements = [];
                    document.getElementById('edit_settlement_search').placeholder = 'Hiba a betöltés során';
                    document.getElementById('edit_settlement_search').disabled = false;
                });
        }

        // Autocomplete funkció a település kereséshez
        const editSearchInput = document.getElementById('edit_settlement_search');
        const editDropdown = document.getElementById('edit_settlement_dropdown');
        const editHiddenInput = document.getElementById('edit_settlement_id');

        if (editSearchInput) {
            editSearchInput.addEventListener('input', function () {
                const searchTerm = this.value.toLowerCase();

                if (searchTerm.length === 0) {
                    editDropdown.style.display = 'none';
                    editHiddenInput.value = '';
                    return;
                }

                const filtered = editSettlements.filter(s =>
                    s.name.toLowerCase().includes(searchTerm)
                );

                if (filtered.length === 0) {
                    editDropdown.style.display = 'none';
                    return;
                }

                editDropdown.innerHTML = '';
                filtered.forEach(settlement => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action';
                    item.textContent = settlement.name;
                    item.onclick = function (e) {
                        e.preventDefault();
                        editSearchInput.value = settlement.name;
                        editHiddenInput.value = settlement.id;
                        editDropdown.style.display = 'none';
                    };
                    editDropdown.appendChild(item);
                });

                editDropdown.style.display = 'block';
            });
        }

        // Kattintás máshova -> bezárás
        document.addEventListener('click', function (e) {
            if (e.target !== editSearchInput) {
                editDropdown.style.display = 'none';
            }
        });

        // County változáskor frissítjük a településeket
        document.addEventListener('DOMContentLoaded', function () {
            const editCountySelect = document.getElementById('edit_county_id');

            if (editCountySelect) {
                editCountySelect.addEventListener('change', function () {
                    const currentSettlement = document.getElementById('edit_settlement_id').value;
                    if (currentSettlement && currentSettlement !== '') { // Check if a settlement is actually selected
                        if (!confirm('Megyeváltás törölni fogja a jelenlegi települést. Folytatod?')) {
                            // Revert county selection
                            this.value = this.getAttribute('data-original-county');
                            return;
                        }
                    }
                    this.setAttribute('data-original-county', this.value);
                    loadEditSettlements(this.value);
                });
            }
        });

        function saveEdit() {
            // Frontend validation for required fields
            const editForm = document.getElementById('editForm');
            if (!editForm.checkValidity()) {
                editForm.reportValidity(); // Show browser's default validation messages
                return;
            }

            // Validáció: település kötelező
            const settlementId = document.getElementById('edit_settlement_id').value;
            if (!settlementId || settlementId === '') {
                alert('A település megadása kötelező!');
                document.getElementById('edit_settlement_id').focus();
                return;
            }

            const clientId = document.getElementById('edit_client_id').value;
            const formData = new FormData();

            formData.append('id', clientId);
            formData.append('name', document.getElementById('edit_name').value);
            formData.append('phone', document.getElementById('edit_phone').value);
            formData.append('email', document.getElementById('edit_email').value);
            formData.append('address', document.getElementById('edit_address').value);
            formData.append('county_id', document.getElementById('edit_county_id').value);
            formData.append('settlement_id', document.getElementById('edit_settlement_id').value);
            formData.append('agent_id', document.getElementById('edit_agent_id').value);
            formData.append('insulation_area', document.getElementById('edit_insulation_area').value);
            formData.append('contract_signed', document.getElementById('edit_contract_signed').checked ? 1 : 0);
            formData.append('work_completed', document.getElementById('edit_work_completed').checked ? 1 : 0);
            formData.append('notes', document.getElementById('edit_notes').value);

            fetch('save_client.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        editModal.hide();
                        location.reload();
                    } else {
                        alert('Hiba: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Hiba történt a mentés során!');
                    console.error(error);
                });
        }
    </script>
</body>

</html>