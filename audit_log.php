<?php
// audit_log.php - Audit Log Admin Felület
require_once 'config.php';
require_once 'audit_helper.php';
Route::protect('auth');

// Csak admin férhet hozzá
if (!isAdmin()) {
    redirect('index.php');
}

// Pagination
$per_page = 50;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Szűrési paraméterek
$filters = [
    'user' => $_GET['filter_user'] ?? '',
    'action' => $_GET['filter_action'] ?? '',
    'table' => $_GET['filter_table'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Assign individual filter variables for backwards compatibility
// so that undefined variable notices do not occur when using
// these values directly in the view. These variables are
// referenced in the form below (e.g. $filter_user, $filter_action). If
// the corresponding GET parameter is not provided, default to an empty string.
$filter_user = $filters['user'];
$filter_action = $filters['action'];
$filter_table = $filters['table'];
$filter_date_from = $filters['date_from'];
$filter_date_to = $filters['date_to'];
$search = $filters['search'];

// Instantiate AuditLogRepository
$auditRepo = new AuditLogRepository($pdo);

// Összes rekord számolása
$total_records = $auditRepo->countAuditLogs($filters);
$total_pages = ceil($total_records / $per_page);

// Logok lekérdezése
$logs = $auditRepo->getAuditLogs($filters, $per_page, $offset);

// Felhasználók, akciók és táblák a szűrőhöz
$users = $auditRepo->getDistinctUsers();
$actions = $auditRepo->getDistinctActions();
$tables = $auditRepo->getDistinctTables();

// Action badge színek
function getActionBadgeClass($action)
{
    return match ($action) {
        'CREATE' => 'bg-success',
        'UPDATE' => 'bg-warning text-dark',
        'DELETE' => 'bg-danger',
        'APPROVE' => 'bg-info',
        'REJECT' => 'bg-secondary',
        'LOGIN' => 'bg-primary',
        'LOGOUT' => 'bg-dark',
        'LOGIN_FAILED' => 'bg-danger',
        'ROLE_CHANGE' => 'bg-purple',
        default => 'bg-secondary'
    };
}

// Action magyar fordítás
function getActionLabel($action)
{
    return match ($action) {
        'CREATE' => 'Létrehozás',
        'UPDATE' => 'Módosítás',
        'DELETE' => 'Törlés',
        'APPROVE' => 'Jóváhagyás',
        'REJECT' => 'Elutasítás',
        'LOGIN' => 'Bejelentkezés',
        'LOGOUT' => 'Kijelentkezés',
        'LOGIN_FAILED' => 'Sikertelen belépés',
        'ROLE_CHANGE' => 'Jogosultság változás',
        default => $action
    };
}

// URL helper
function buildFilterUrl($params_override = [])
{
    $current_params = [
        'filter_user' => $_GET['filter_user'] ?? '',
        'filter_action' => $_GET['filter_action'] ?? '',
        'filter_table' => $_GET['filter_table'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'search' => $_GET['search'] ?? '',
        'page' => $_GET['page'] ?? 1
    ];
    $params = array_merge($current_params, $params_override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 1);
    return 'audit_log.php' . ($params ? '?' . http_build_query($params) : '');
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Audit Log - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/crm-complete.css?v=<?php echo APP_VERSION; ?>">

    <script src="assets/js/error-logger.js"></script>
</head>

<body class="page-audit-log">
    <!-- Header -->
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Vissza
                    </a>
                    <h3 class="mb-0"><i class="bi bi-journal-text me-2"></i>Audit Log</h3>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success btn-sm" onclick="exportCSV()">
                        <i class="bi bi-download"></i> Export CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statisztikák -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="fs-4 fw-bold"><?php echo number_format($total_records); ?></div>
                            <div class="small opacity-75">Összes log</div>
                        </div>
                        <i class="bi bi-journal-text fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
            <?php
            $today_count = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            $week_count = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $delete_count = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'DELETE'")->fetchColumn();
            ?>
            <div class="col-md-3 col-6 mb-3">
                <div class="stats-card success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="fs-4 fw-bold"><?php echo number_format($today_count); ?></div>
                            <div class="small opacity-75">Mai műveletek</div>
                        </div>
                        <i class="bi bi-calendar-check fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="stats-card info">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="fs-4 fw-bold"><?php echo number_format($week_count); ?></div>
                            <div class="small opacity-75">Heti műveletek</div>
                        </div>
                        <i class="bi bi-graph-up fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="stats-card warning">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="fs-4 fw-bold"><?php echo number_format($delete_count); ?></div>
                            <div class="small opacity-75">Törlések összesen</div>
                        </div>
                        <i class="bi bi-trash fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Szűrők -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label small">Felhasználó</label>
                        <select name="filter_user" class="form-select form-select-sm">
                            <option value="">Mind</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>" <?php echo $filter_user == $user['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($user['user_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Művelet</label>
                        <select name="filter_action" class="form-select form-select-sm">
                            <option value="">Mind</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo $action; ?>" <?php echo $filter_action == $action ? 'selected' : ''; ?>>
                                    <?php echo getActionLabel($action); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Tábla</label>
                        <select name="filter_table" class="form-select form-select-sm">
                            <option value="">Mind</option>
                            <?php foreach ($tables as $table): ?>
                                <option value="<?php echo $table; ?>" <?php echo $filter_table == $table ? 'selected' : ''; ?>>
                                    <?php echo escape($table); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Dátum -tól</label>
                        <input type="date" name="date_from" class="form-control form-control-sm"
                            value="<?php echo escape($filter_date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Dátum -ig</label>
                        <input type="date" name="date_to" class="form-control form-control-sm"
                            value="<?php echo escape($filter_date_to); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Keresés</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Keresés..."
                            value="<?php echo escape($search); ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i>
                            Szűrés</button>
                        <a href="audit_log.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i>
                            Törlés</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Táblázat -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Időpont</th>
                            <th>Felhasználó</th>
                            <th>Művelet</th>
                            <th>Tábla</th>
                            <th>Rekord ID</th>
                            <th>Régi érték</th>
                            <th>Új érték</th>
                            <th>IP cím</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Nincs találat a megadott szűrőkkel
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <small><?php echo date('Y.m.d H:i:s', strtotime($log['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo escape($log['user_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getActionBadgeClass($log['action']); ?>">
                                            <?php echo getActionLabel($log['action']); ?>
                                        </span>
                                    </td>
                                    <td><code><?php echo escape($log['table_name']); ?></code></td>
                                    <td><?php echo escape($log['record_id']); ?></td>
                                    <td>
                                        <?php if ($log['old_values']): ?>
                                            <div class="json-preview" onclick="showJsonModal('Régi érték', this.dataset.json)"
                                                data-json="<?php echo escape($log['old_values']); ?>">
                                                <?php echo escape(mb_substr($log['old_values'], 0, 50)) . '...'; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['new_values']): ?>
                                            <div class="json-preview" onclick="showJsonModal('Új érték', this.dataset.json)"
                                                data-json="<?php echo escape($log['new_values']); ?>">
                                                <?php echo escape(mb_substr($log['new_values'], 0, 50)) . '...'; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo escape($log['ip_address']); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildFilterUrl(['page' => $page - 1]); ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                        ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo buildFilterUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildFilterUrl(['page' => $page + 1]); ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
                <p class="text-center text-muted small">
                    Összesen <?php echo number_format($total_records); ?> bejegyzés, <?php echo $total_pages; ?> oldal
                </p>
            </nav>
        <?php endif; ?>
    </div>

    <!-- JSON Modal -->
    <div class="modal fade" id="jsonModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="jsonModalTitle">Részletek</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="jsonContent" class="modal-pre"></pre>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const jsonModal = new bootstrap.Modal(document.getElementById('jsonModal'));

        function showJsonModal(title, jsonString) {
            document.getElementById('jsonModalTitle').textContent = title;
            try {
                const parsed = JSON.parse(jsonString);
                document.getElementById('jsonContent').textContent = JSON.stringify(parsed, null, 2);
            } catch (e) {
                document.getElementById('jsonContent').textContent = jsonString;
            }
            jsonModal.show();
        }

        function exportCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'audit_log_export.php?' + params.toString();
        }
    </script>
</body>

</html>