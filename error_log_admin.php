<?php
require_once 'config.php';
Route::protect('admin');

$errorLogRepo = new ErrorLogRepository($pdo);

// Handle actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_all'])) {
        $errorLogRepo->clearAll();
        $success = 'Minden hiba törölve!';
    } elseif (isset($_POST['delete_old'])) {
        $days = (int) ($_POST['days'] ?? 30);
        $deleted = $errorLogRepo->deleteOldErrors($days);
        $success = "$deleted régi hiba törölve ($days napnál régebbi)!";
    }
}

// Filters
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;

$filters = [
    'error_type' => $_GET['filter_type'] ?? '',
    'severity' => $_GET['filter_severity'] ?? '',
    'user_id' => isset($_GET['filter_user']) && $_GET['filter_user'] !== '' ? (int) $_GET['filter_user'] : null,
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Get errors
$errors = $errorLogRepo->getAllErrors($page, $perPage, $filters);
$totalErrors = $errorLogRepo->countErrors($filters);
$totalPages = ceil($totalErrors / $perPage);

// Get statistics
$stats = $errorLogRepo->getStatistics('today');
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hibanapló - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/crm-complete.css?v=<?php echo APP_VERSION; ?>">
    <style>
        .severity-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .severity-FATAL {
            background-color: #dc3545;
        }

        .severity-ERROR {
            background-color: #fd7e14;
        }

        .severity-WARNING {
            background-color: #ffc107;
            color: #000;
        }

        .severity-NOTICE {
            background-color: #0dcaf0;
        }

        .error-message {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
        }

        .stats-card {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .error-details {
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 5px;
        }
    </style>

    <script src="assets/js/error-logger.js"></script>
</head>

<body>
    <div class="header py-3 mb-4">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><?php echo APP_NAME; ?></h3>
                <div class="d-flex align-items-center gap-3">
                    <a href="profile.php" class="text-decoration-none text-dark">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <span class="badge bg-primary ms-2">Admin</span>
                    </a>
                    <a href="admin.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Admin
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Kijelentkezés
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo escape($success); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card bg-primary text-white">
                    <h4><?php echo $stats['total']; ?></h4>
                    <p class="mb-0">Összes hiba (ma)</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card bg-danger text-white">
                    <h4><?php echo $stats['by_severity']['FATAL'] ?? 0; ?></h4>
                    <p class="mb-0">Fatal hibák</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card bg-warning">
                    <h4><?php echo $stats['by_severity']['ERROR'] ?? 0; ?></h4>
                    <p class="mb-0">Error hibák</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card bg-info">
                    <h4><?php echo ($stats['by_severity']['WARNING'] ?? 0) + ($stats['by_severity']['NOTICE'] ?? 0); ?>
                    </h4>
                    <p class="mb-0">Warnings & Notices</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-funnel"></i> Szűrők
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Típus</label>
                        <select name="filter_type" class="form-select">
                            <option value="">Összes</option>
                            <option value="PHP_ERROR" <?php echo $filters['error_type'] === 'PHP_ERROR' ? 'selected' : ''; ?>>PHP Error</option>
                            <option value="EXCEPTION" <?php echo $filters['error_type'] === 'EXCEPTION' ? 'selected' : ''; ?>>Exception</option>
                            <option value="JS_ERROR" <?php echo $filters['error_type'] === 'JS_ERROR' ? 'selected' : ''; ?>>JS Error</option>
                            <option value="APP_ERROR" <?php echo $filters['error_type'] === 'APP_ERROR' ? 'selected' : ''; ?>>App Error</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Súlyosság</label>
                        <select name="filter_severity" class="form-select">
                            <option value="">Összes</option>
                            <option value="FATAL" <?php echo $filters['severity'] === 'FATAL' ? 'selected' : ''; ?>>Fatal
                            </option>
                            <option value="ERROR" <?php echo $filters['severity'] === 'ERROR' ? 'selected' : ''; ?>>Error
                            </option>
                            <option value="WARNING" <?php echo $filters['severity'] === 'WARNING' ? 'selected' : ''; ?>>
                                Warning</option>
                            <option value="NOTICE" <?php echo $filters['severity'] === 'NOTICE' ? 'selected' : ''; ?>>
                                Notice</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Dátum-tól</label>
                        <input type="date" name="date_from" class="form-control"
                            value="<?php echo escape($filters['date_from']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Dátum-ig</label>
                        <input type="date" name="date_to" class="form-control"
                            value="<?php echo escape($filters['date_to']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Keresés</label>
                        <input type="text" name="search" class="form-control" placeholder="Üzenet vagy fájl..."
                            value="<?php echo escape($filters['search']); ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Szűrés</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Errors Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bug"></i> Hibák (<?php echo $totalErrors; ?>)</span>
                <div>
                    <form method="post" class="d-inline"
                        onsubmit="return confirm('Biztosan törölni szeretnéd az összes hibát?');">
                        <button type="submit" name="clear_all" class="btn btn-danger btn-sm">
                            <i class="bi bi-trash"></i> Összes törlése
                        </button>
                    </form>
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal"
                        data-bs-target="#deleteOldModal">
                        <i class="bi bi-clock-history"></i> Régiek törlése
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($errors)): ?>
                    <div class="alert alert-info m-3">Nincsenek hibák a kiválasztott szűrőkkel.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="col-id">ID</th>
                                    <th class="col-type">Típus</th>
                                    <th class="col-severity">Súlyosság</th>
                                    <th class="col-message">Üzenet</th>
                                    <th class="col-file">Fájl</th>
                                    <th class="col-time">Időpont</th>
                                    <th class="col-details">Részletek</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($errors as $err): ?>
                                    <tr>
                                        <td><?php echo $err['id']; ?></td>
                                        <td><span class="badge bg-secondary"><?php echo escape($err['error_type']); ?></span>
                                        </td>
                                        <td><span
                                                class="badge severity-badge severity-<?php echo $err['severity']; ?>"><?php echo escape($err['severity']); ?></span>
                                        </td>
                                        <td class="error-message">
                                            <?php echo escape(substr($err['message'], 0, 100)); ?>
                                            <?php echo strlen($err['message']) > 100 ? '...' : ''; ?>
                                        </td>
                                        <td><small><?php echo escape($err['file'] ? basename($err['file']) : '-'); ?><?php echo $err['line'] ? ':' . $err['line'] : ''; ?></small>
                                        </td>
                                        <td><small><?php echo date('Y-m-d H:i:s', strtotime($err['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info"
                                                onclick="showErrorDetails(<?php echo htmlspecialchars(json_encode($err), ENT_QUOTES); ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="p-3">
                            <nav>
                                <ul class="pagination mb-0">
                                    <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link"
                                                href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Old Modal -->
    <div class="modal fade" id="deleteOldModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Régi hibák törlése</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Törlés ennél régebbiek:</label>
                        <select name="days" class="form-select">
                            <option value="7">7 napnál régebbi</option>
                            <option value="30" selected>30 napnál régebbi</option>
                            <option value="60">60 napnál régebbi</option>
                            <option value="90">90 napnál régebbi</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mégse</button>
                        <button type="submit" name="delete_old" class="btn btn-warning">Törlés</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Error Details Modal -->
    <div class="modal fade" id="errorDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Hiba részletek</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="errorDetailContent">
                    <!-- Dynamic content -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showErrorDetails(error) {
            const content = `
                <div class="row g-3">
                    <div class="col-12"><strong>ID:</strong> ${error.id}</div>
                    <div class="col-6"><strong>Típus:</strong> <span class="badge bg-secondary">${error.error_type}</span></div>
                    <div class="col-6"><strong>Súlyosság:</strong> <span class="badge severity-${error.severity}">${error.severity}</span></div>
                    <div class="col-12"><strong>Üzenet:</strong><div class="error-details">${escapeHtml(error.message)}</div></div>
                    ${error.file ? `<div class="col-12"><strong>Fájl:</strong> ${escapeHtml(error.file)}${error.line ? ` (Line: ${error.line})` : ''}</div>` : ''}
                    ${error.url ? `<div class="col-12"><strong>URL:</strong> ${escapeHtml(error.url)}</div>` : ''}
                    ${error.user_agent ? `<div class="col-12"><strong>User Agent:</strong> <small>${escapeHtml(error.user_agent)}</small></div>` : ''}
                    ${error.ip_address ? `<div class="col-6"><strong>IP:</strong> ${escapeHtml(error.ip_address)}</div>` : ''}
                    ${error.user_id ? `<div class="col-6"><strong>User ID:</strong> ${error.user_id}</div>` : ''}
                    <div class="col-12"><strong>Időpont:</strong> ${error.created_at}</div>
                    ${error.trace ? `<div class="col-12"><strong>Stack Trace:</strong><pre class="error-details">${escapeHtml(error.trace)}</pre></div>` : ''}
                </div>
            `;
            document.getElementById('errorDetailContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('errorDetailModal')).show();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>

</html>