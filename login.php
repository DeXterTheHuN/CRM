<!-- Server timestamp: <?php echo date('Y-m-d H:i:s'); ?> -->
<!-- File: login.php -->
<?php
require_once 'config.php';
require_once 'audit_helper.php';

// Ha már be van jelentkezve, átirányítás
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

// Instantiate user repository for user operations
$userRepo = new UserRepository($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        // Attempt to load the user by username via the repository
        $user = $userRepo->getUserByUsername($username);

        if ($user && password_verify($password, $user['password'])) {
            // Ellenőrizzük a jóváhagyást - using approval_status enum
            if ($user['approval_status'] !== 'approved') {
                $error = 'A fiókod még nincs jóváhagyva. Kérlek, várd meg az adminisztrátor jóváhagyását!';
                // Sikertelen bejelentkezés logolása (nem jóváhagyott)
                logAudit($pdo, 'LOGIN_BLOCKED', 'users', $user['id'], null, ['reason' => 'not_approved']);
            } else {
                // Session fixation védelem: új session ID generálása
                session_regenerate_id(true);

                // Sikeres bejelentkezés
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['approval_status'] = $user['approval_status'];

                // Utolsó bejelentkezés frissítése via repository
                $userRepo->updateLastLogin($user['id']);

                // Sikeres bejelentkezés logolása
                logLogin($pdo, $user['id'], true);

                redirect('index.php');
            }
        } else {
            $error = 'Hibás felhasználónév vagy jelszó!';
            // Sikertelen bejelentkezés logolása
            if ($user) {
                logLogin($pdo, $user['id'], false);
            } else {
                logAudit($pdo, 'LOGIN_FAILED', 'users', null, null, ['username' => $username, 'reason' => 'user_not_found']);
            }
        }
    } else {
        $error = 'Kérlek, töltsd ki az összes mezőt!';
    }
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bejelentkezés - <?php echo APP_NAME; ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/crm-complete.css?v=<?php echo APP_VERSION; ?>">

    <script src="assets/js/error-logger.js"></script>
</head>

<body class="auth-page">

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="auth-logo-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h1 class="auth-title"><?php echo APP_NAME; ?></h1>
                <p class="auth-subtitle">Bejelentkezés a fiókodba</p>
            </div>

            <?php if ($error): ?>
                <div class="auth-alert danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="auth-form-group">
                    <label for="username" class="auth-form-label">Felhasználónév</label>
                    <div class="auth-input-wrapper">
                        <input type="text" class="auth-input" id="username" name="username" required autofocus
                            autocomplete="username">
                        <i class="fas fa-user auth-input-icon"></i>
                    </div>
                </div>

                <div class="auth-form-group">
                    <label for="password" class="auth-form-label">Jelszó</label>
                    <div class="auth-input-wrapper">
                        <input type="password" class="auth-input" id="password" name="password" required
                            autocomplete="current-password">
                        <i class="fas fa-lock auth-input-icon"></i>
                    </div>
                </div>

                <button type="submit" class="auth-btn">
                    <i class="fas fa-sign-in-alt"></i> Bejelentkezés
                </button>
            </form>

            <div class="auth-link-section">
                <p>Nincs még fiókod? <a href="register.php">Regisztrálj most</a></p>
            </div>
        </div>
    </div>

</body>

</html>