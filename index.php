<?php
require_once 'config.php';
Route::protect('auth');

// Megyék lekérdezése ügyfélszámmal cache-eléssel (repository használatával)
// A counties_with_counts kulcs alatt tároljuk a megyék listáját az ügyfélszámokkal együtt.
// Mivel az új ügyfelek és jóváhagyások dinamikusan frissítik az ügyfélszámot, a cache
// élettartamát rövidebbre (5 perc) állítjuk, így a számok viszonylag frissen maradnak.
$counties = cache_get('counties_with_counts', CACHE_TTL_SHORT);
if ($counties === false) {
    $countyRepo = new CountyRepository($pdo);
    $counties = $countyRepo->getCountiesWithCounts();
    // Cache-be mentés
    cache_set('counties_with_counts', $counties);
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/crm-complete.css?v=<?php echo APP_VERSION; ?>">

    <!-- SweetAlert2 for modern, mobile-friendly dialogs -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/error-logger.js"></script>
</head>

<body>
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="mb-0"><?php echo APP_NAME; ?></h3>
                <div class="d-flex align-items-center gap-2 gap-md-3 flex-wrap justify-content-end">
                    <a href="profile.php" class="text-decoration-none text-dark">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <?php if (isAdmin()): ?>
                            <span class="badge bg-primary ms-2">Admin</span>
                        <?php endif; ?>
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="statistics.php" class="btn btn-outline-success btn-sm" title="Statisztikák">
                            <i class="bi bi-graph-up"></i> <span class="d-none d-md-inline">Statisztikák</span>
                        </a>
                        <a href="approvals.php" class="btn btn-outline-warning btn-sm position-relative badge-container"
                            title="Jóváhagyások" id="approvalsLink">
                            <i class="bi bi-clock-history"></i> <span class="d-none d-md-inline">Jóváhagyások</span>
                            <span class="badge bg-danger rounded-pill notification-badge" id="approvalsBadge">0</span>
                        </a>
                        <a href="admin.php" class="btn btn-outline-primary btn-sm" title="Felhasználók">
                            <i class="bi bi-people-fill"></i> <span class="d-none d-md-inline">Felhasználók</span>
                        </a>
                    <?php else: ?>
                        <a href="my_requests.php" class="btn btn-outline-info btn-sm position-relative badge-container"
                            title="Saját Kérések" id="myRequestsLink">
                            <i class="bi bi-file-earmark-text"></i> <span class="d-none d-md-inline">Saját Kérések</span>
                            <span class="badge bg-info rounded-pill notification-badge" id="myRequestsBadge">0</span>
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm" title="Kijelentkezés">
                        <i class="bi bi-box-arrow-right"></i> <span class="d-none d-md-inline">Kijelentkezés</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="mb-4">
            <h2>Válassz megyét</h2>
            <p class="text-muted">Kattints egy megyére az ügyfelek megtekintéséhez és kezeléséhez</p>
        </div>

        <div class="row g-3">
            <?php foreach ($counties as $county): ?>
                <div class="col-md-4">
                    <a href="county.php?id=<?php echo $county['id']; ?>" class="text-decoration-none">
                        <div class="card county-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded p-3 me-3">
                                        <i class="bi bi-geo-alt-fill text-primary fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-0 text-dark"><?php echo escape($county['name']); ?></h5>
                                                <small class="text-muted">
                                                    <i class="bi bi-people-fill"></i>
                                                    <?php echo $county['client_count']; ?> ügyfél
                                                </small>
                                            </div>
                                            <span class="badge bg-success new-client-badge notification-badge"
                                                data-county-id="<?php echo $county['id']; ?>">0
                                                új</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Toast container -->
    <div class="toast-container-fixed">
        <div id="toastContainer"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/crm-main.js?v=<?php echo APP_VERSION; ?>"></script>
</body>

</html>