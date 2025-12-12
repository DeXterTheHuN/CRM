<?php
require_once 'config.php';
Route::protect('admin');

// Útvonal a PHP error log fájlhoz
$logFile = __DIR__ . '/error_log';

// Hibaüzenetek beolvasása soronként
$errors = [];
if (file_exists($logFile)) {
    // Igaz, ha a fájl soronként olvasható
    $errors = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

// Napló törlésének kezelése
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_log'])) {
    // A fájl tartalmának ürítése
    file_put_contents($logFile, '');
    $errors = [];
    $success = 'A hibanapló sikeresen törölve!';
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Hibanapló - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/crm-complete.css?v=2.3">
    <style>
        .log-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            margin-bottom: 0;
        }
    </style>
</head>

<body class="page-error-log">
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><?php echo APP_NAME; ?></h3>
                <div class="d-flex align-items-center gap-3">
                    <a href="profile.php" class="text-decoration-none text-dark">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <span class="badge bg-primary ms-2">Admin</span>
                    </a>
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
            <a href="admin.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Vissza az admin oldalra
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo escape($success); ?></div>
        <?php endif; ?>

        <div class="log-table p-4">
            <h4 class="mb-3"><i class="bi bi-bug"></i> Hibanapló</h4>
            <?php if (empty($errors)): ?>
                <div class="alert alert-info">Nincsenek hibaüzenetek.</div>
            <?php else: ?>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th>Üzenet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($errors) as $index => $line): ?>
                            <tr>
                                <td><?php echo count($errors) - $index; ?></td>
                                <td>
                                    <pre><?php echo escape($line); ?></pre>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <form method="post" class="mt-3">
                    <button type="submit" name="clear_log" class="btn btn-danger"
                        onclick="return confirm('Biztosan törölni szeretnéd a hibanaplót?');">
                        <i class="bi bi-trash"></i> Napló törlése
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>