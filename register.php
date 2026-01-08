<?php
require_once 'config.php';

// Ha már be van jelentkezve, átirányítás
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

// Instantiate user repository
$userRepo = new UserRepository($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validáció
    if (!$username || !$password || !$name) {
        $error = 'A felhasználónév, jelszó és név megadása kötelező!';
    } elseif (strlen($username) < USERNAME_MIN_LENGTH) {
        $error = 'A felhasználónév legalább ' . USERNAME_MIN_LENGTH . ' karakter hosszú legyen!';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'A jelszó legalább ' . PASSWORD_MIN_LENGTH . ' karakter hosszú legyen!';
    } elseif ($password !== $password_confirm) {
        $error = 'A két jelszó nem egyezik!';
    } else {
        // Ellenőrizzük, hogy létezik-e már a felhasználónév a repository segítségével
        if ($userRepo->userExists($username)) {
            $error = 'Ez a felhasználónév már foglalt!';
        } else {
            // Regisztráció a repository használatával
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $userRepo->createUser($username, $hashed_password, $name, $email, 'agent', 'pending');
                $success = 'Sikeres regisztráció! A fiókod jóváhagyásra vár. Az adminisztrátor jóváhagyása után bejelentkezhetsz.';
                // Átirányítás pár másodperc után
                header("refresh:" . REGISTER_REDIRECT_DELAY . ";url=login.php");
            } catch (Exception $e) {
                $error = 'Hiba történt a regisztráció során. Kérlek, próbáld újra!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regisztráció - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/crm-complete.css?v=<?php echo APP_VERSION; ?>">

    <script src="assets/js/error-logger.js"></script>
</head>

<body class="auth-page">
    <div class="auth-container wide">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="auth-logo-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1 class="auth-title"><?php echo APP_NAME; ?></h1>
                <p class="auth-subtitle">Hozd létre az új fiókodat</p>
            </div>

            <?php if ($error): ?>
                <div class="auth-alert danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="auth-success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="auth-alert success">
                    <strong>Sikeres regisztráció!</strong><br>
                    <?php echo escape($success); ?><br>
                    <small><i class="fas fa-spinner fa-spin"></i> Átirányítás a bejelentkezési oldalra...</small>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="auth-form-group compact">
                        <label for="name" class="auth-form-label">Teljes név <span class="required">*</span></label>
                        <div class="auth-input-wrapper">
                            <input type="text" class="auth-input compact" id="name" name="name"
                                value="<?php echo escape($_POST['name'] ?? ''); ?>" required autofocus autocomplete="name">
                            <i class="fas fa-user auth-input-icon small"></i>
                        </div>
                    </div>

                    <div class="auth-row">
                        <div class="auth-col-6">
                            <div class="auth-form-group compact">
                                <label for="username" class="auth-form-label">Felhasználónév <span
                                        class="required">*</span></label>
                                <div class="auth-input-wrapper">
                                    <input type="text" class="auth-input compact" id="username" name="username"
                                        value="<?php echo escape($_POST['username'] ?? ''); ?>" required
                                        autocomplete="username">
                                    <i class="fas fa-at auth-input-icon small"></i>
                                </div>
                                <small class="auth-form-text">Legalább 3 karakter</small>
                            </div>
                        </div>

                        <div class="auth-col-6">
                            <div class="auth-form-group compact">
                                <label for="email" class="auth-form-label">E-mail cím</label>
                                <div class="auth-input-wrapper">
                                    <input type="email" class="auth-input compact" id="email" name="email"
                                        value="<?php echo escape($_POST['email'] ?? ''); ?>" autocomplete="email">
                                    <i class="fas fa-envelope auth-input-icon small"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="auth-row">
                        <div class="auth-col-6">
                            <div class="auth-form-group compact">
                                <label for="password" class="auth-form-label">Jelszó <span class="required">*</span></label>
                                <div class="auth-input-wrapper">
                                    <input type="password" class="auth-input compact" id="password" name="password" required
                                        autocomplete="new-password">
                                    <i class="fas fa-lock auth-input-icon small"></i>
                                </div>
                                <small class="auth-form-text">Legalább 6 karakter</small>
                            </div>
                        </div>

                        <div class="auth-col-6">
                            <div class="auth-form-group compact">
                                <label for="password_confirm" class="auth-form-label">Jelszó újra <span
                                        class="required">*</span></label>
                                <div class="auth-input-wrapper">
                                    <input type="password" class="auth-input compact" id="password_confirm"
                                        name="password_confirm" required autocomplete="new-password">
                                    <i class="fas fa-lock auth-input-icon small"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="auth-btn spaced">
                        <i class="fas fa-user-plus"></i> Regisztráció
                    </button>
                </form>

                <div class="auth-link-section">
                    <p>Már van fiókod? <a href="login.php">Jelentkezz be</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>