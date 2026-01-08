<?php
require_once 'config.php';
Route::protect('admin');

// Instantiate repositories for user and agent operations
$userRepo = new UserRepository($pdo);
$agentRepo = new AgentRepository($pdo);

$success = '';
$error = '';

// Felhasználó törlése (POST - CSRF védelem)
if (isset($_POST['delete_user'])) {
    $user_id = (int) $_POST['user_id'];
    // Ne lehessen önmagát törölni
    if ($user_id == $_SESSION['user_id']) {
        $error = 'Nem törölheted a saját fiókodat!';
    } else {
        // Use repository to delete non-admin user
        $userRepo->deleteUser($user_id);
        $success = 'Felhasználó sikeresen törölve!';
    }
}

// Szerepkör változtatása
if (isset($_POST['change_role'])) {
    $user_id = (int) $_POST['user_id'];
    $new_role = $_POST['role'];
    if ($user_id == $_SESSION['user_id']) {
        $error = 'Nem változtathatod meg a saját szerepkörödet!';
    } elseif (in_array($new_role, ['admin', 'agent'])) {
        // Use repository to update role
        $userRepo->updateUserRole($user_id, $new_role);
        $success = 'Szerepkör sikeresen megváltoztatva!';
    }
}

// Ügyintéző színének megváltoztatása
if (isset($_POST['change_color'])) {
    $agent_id = (int) $_POST['agent_id'];
    $new_color = $_POST['color'];
    // Szín validálás (hexadecimális formátum)
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $new_color)) {
        // Use repository to update colour
        $agentRepo->updateColor($agent_id, $new_color);
        $success = 'Ügyintéző színe sikeresen megváltoztatva!';
    } else {
        $error = 'Helytelen színkód formátum!';
    }
}

// Felhasználó jóváhagyása
if (isset($_POST['approve_user'])) {
    $user_id = (int) $_POST['user_id'];
    $userRepo->approveUser($user_id);
    $success = 'Felhasználó sikeresen jóváhagyva!';
}

// Felhasználó jóváhagyásának visszavonása
if (isset($_POST['unapprove_user'])) {
    $user_id = (int) $_POST['user_id'];
    if ($user_id != $_SESSION['user_id']) {
        $userRepo->unapproveUser($user_id);
        $success = 'Felhasználó jóváhagyása visszavonva!';
    } else {
        $error = 'Nem vonhatod vissza a saját jóváhagyásodat!';
    }
}

// Összes felhasználó lekérdezése (jóváhagyásra várók elől)
$users = $userRepo->getAllUsers();

// Jóváhagyásra váró felhasználók száma
$pending_count = $userRepo->getPendingApprovalCount();

// Minden felhasználó számára automatikusan létrehozzuk az agents rekordot, ha még nem létezik
$userRepo->ensureDefaultAgents($agentRepo, '#808080');

// Minden felhasználó (admin és ügyintéző) színbeállításai
$agents = $agentRepo->getAgentsWithRoles();
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Admin - Felhasználók - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/crm-complete.css?v=<?php echo APP_VERSION; ?>">

    <script src="assets/js/error-logger.js"></script>
</head>

<body class="page-admin">
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><?php echo APP_NAME; ?></h3>
                <div class="d-flex align-items-center gap-3">
                    <a href="profile.php" class="text-decoration-none text-dark">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <span class="badge bg-primary ms-2">Admin</span>
                    </a>

                    <!-- Hibanapló gomb az adminok számára -->
                    <a href="error_log_admin.php" class="btn btn-outline-warning">
                        <i class="bi bi-exclamation-triangle"></i> Hibanapló
                    </a>

                    <a href="audit_log.php" class="btn btn-outline-info">
                        <i class="bi bi-journal-text"></i> Audit Log
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

            <!-- Egységes Export Dropdown - Admin Only -->
            <div class="btn-group">
                <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <h6 class="dropdown-header"><i class="bi bi-file-earmark-spreadsheet"></i> Adatok Exportálása
                        </h6>
                    </li>
                    <li><a class="dropdown-item" href="export_statistics.php">
                            <i class="bi bi-graph-up text-primary"></i> Statisztikák (Excel)
                        </a></li>
                    <li><a class="dropdown-item" href="export_counties.php">
                            <i class="bi bi-geo-alt text-info"></i> Megyék - Összes Ügyfél (Excel)
                        </a></li>
                    <li><a class="dropdown-item" href="export_agents.php">
                            <i class="bi bi-people text-success"></i> Ügyintézők (Excel)
                        </a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="audit_log_export.php">
                            <i class="bi bi-journal-text text-warning"></i> Audit Log (Excel)
                        </a></li>
                </ul>
            </div>

        </div>

        <div class="admin-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <i class="bi bi-people-fill"></i> Felhasználók Kezelése
                </h4>
                <?php if ($pending_count > 0): ?>
                    <span class="badge bg-warning text-dark fs-6">
                        <i class="bi bi-clock-history"></i> <?php echo $pending_count; ?> jóváhagyásra vár
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo escape($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo escape($success); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Név</th>
                            <th>Felhasználónév</th>
                            <th>E-mail</th>
                            <th>Szerepkör</th>
                            <th>Jóváhagyás</th>
                            <th>Regisztráció</th>
                            <th>Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr class="user-row">
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <strong><?php echo escape($user['name']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-info text-dark">Te</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo escape($user['username']); ?></td>
                                <td><?php echo escape($user['email'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge bg-primary">Adminisztrátor</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ügyintéző</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['approval_status'] === 'approved'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Jóváhagyva</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Várakozás</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <!-- Jóváhagyás -->
                                        <?php if ($user['approval_status'] !== 'approved'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="approve_user" class="btn btn-sm btn-success"
                                                    title="Felhasználó jóváhagyása">
                                                    <i class="bi bi-check-circle"></i> Jóváhagy
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="unapprove_user" class="btn btn-sm btn-outline-warning"
                                                    title="Jóváhagyás visszavonása"
                                                    onclick="return confirm('Biztosan visszavonod a jóváhagyást?')">
                                                    <i class="bi bi-x-circle"></i> Visszavon
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Szerepkör váltás -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <input type="hidden" name="role" value="agent">
                                                <button type="submit" name="change_role" class="btn btn-sm btn-warning"
                                                    title="Ügyintézővé alakítás"
                                                    onclick="return confirm('Biztosan ügyintézővé alakítod?')">
                                                    <i class="bi bi-arrow-down-circle"></i>
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="role" value="admin">
                                                <button type="submit" name="change_role" class="btn btn-sm btn-success"
                                                    title="Adminná alakítás" onclick="return confirm('Biztosan adminná alakítod?')">
                                                    <i class="bi bi-arrow-up-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Törlés -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Biztosan törölni szeretnéd ezt a felhasználót?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <div class="alert alert-info">
                    <strong><i class="bi bi-info-circle"></i> Tipp:</strong>
                    Az új ügyintézők a <a href="register.php" target="_blank">regisztrációs oldalon</a> tudnak
                    regisztrálni.
                    Alapértelmezés szerint "Ügyintéző" szerepkört kapnak, amit itt tudsz módosítani.
                </div>
            </div>

            <!-- Felhasználók Színbeállítása -->
            <div class="mt-5">
                <h4 class="mb-4">
                    <i class="bi bi-palette-fill"></i> Felhasználók Színbeállítása
                </h4>
                <p class="text-muted">A felhasználókhoz rendelt színek a megyei listákban a sorok háttérszínét
                    határozzák meg.</p>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Felhasználó Név</th>
                                <th>Szerepkör</th>
                                <th>Jelenlegi Szín</th>
                                <th>Szín Előnézet</th>
                                <th>Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agents as $agent): ?>
                                <tr>
                                    <td><?php echo $agent['id']; ?></td>
                                    <td><strong><?php echo escape($agent['name']); ?></strong></td>
                                    <td>
                                        <?php if ($agent['role'] === 'admin'): ?>
                                            <span class="badge bg-primary">Adminisztrátor</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Ügyintéző</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo escape($agent['color'] ?? '#808080'); ?></code></td>
                                    <td>
                                        <div class="color-swatch"
                                            style="background-color: <?php echo escape($agent['color'] ?? '#808080'); ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline-flex align-items-center gap-2">
                                            <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                                            <input type="color" name="color" class="form-control form-control-color"
                                                value="<?php echo escape($agent['color'] ?? '#808080'); ?>"
                                                title="Válassz színt">
                                            <button type="submit" name="change_color" class="btn btn-sm btn-primary">
                                                <i class="bi bi-check-circle"></i> Mentés
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-warning mt-3">
                    <strong><i class="bi bi-exclamation-triangle"></i> Megjegyzés:</strong>
                    A színek 15% átlátszósággal jelennek meg a listákban a jobb olvashatóság érdekében.
                    Ajánlott világos és könnyen megkülönböztethető színeket választani.
                </div>
            </div>

            <div class="mt-3">
                <h5>Statisztikák</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?php echo count($users); ?></h3>
                                <p class="mb-0">Összes felhasználó</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="text-success">
                                    <?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?>
                                </h3>
                                <p class="mb-0">Adminisztrátor</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="text-secondary">
                                    <?php echo count(array_filter($users, fn($u) => $u['role'] === 'agent')); ?>
                                </h3>
                                <p class="mb-0">Ügyintéző</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>