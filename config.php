<?php
// Adatbázis konfiguráció
// FONTOS: Módosítsd ezeket az értékeket a saját cPanel adatbázis adataidra!

define('DB_HOST', 'localhost');
define('DB_NAME', 'szabolcs_padlas_crm');
define('DB_USER', 'szabolcs_admin');
define('DB_PASS', 'kicsi2001');
define('DB_CHARSET', 'utf8mb4');

// Betöltjük az alkalmazás konstansokat
require_once __DIR__ . '/constants.php';

// Session konfiguráció
define('SESSION_NAME', 'padlas_crm_session');
// Átnevezve SESSION_LIFETIME_SECONDS-ra a constants.php-ban

// Alkalmazás konfiguráció
define('APP_NAME', 'Padlás Födém Szigetelés CRM');
define('APP_URL', 'https://crm.szabolcsutep.hu/');

// Időzóna
date_default_timezone_set('Europe/Budapest');

// Hibakezelés (éles környezetben kapcsold ki!)
//ini_set('display_errors', 1);
// Error Handler betöltése
require_once __DIR__ . '/ErrorHandler.php';

// Error Handler inicializálása
// FONTOS: Production környezetben állítsd false-ra a debug módot!
// Auto-detect: localhost = debug, egyébként production
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);
ErrorHandler::init($debugMode = $isLocal);

// Helper függvények
require_once __DIR__ . '/cache_helper.php';

// Middleware rendszer betöltése
require_once __DIR__ . '/Middleware.php';

// PDO kapcsolat létrehozása
try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];
    
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        $options
    );
} catch (PDOException $e) {
    // Naplózzuk a részletes hibát, de ne jelenítsük meg a felhasználónak
    ErrorHandler::logAppError('Database connection failed: ' . $e->getMessage(), [
        'host' => DB_HOST,
        'database' => DB_NAME
    ]);
    
    // Production módban csak általános hibaüzenet
    if (!$debugMode) {
        die('Adatbázis kapcsolódási hiba. Kérjük, próbáld meg később.');
    } else {
        die("Adatbázis kapcsolódási hiba: " . $e->getMessage());
    }
}

// Session indítása
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Helper függvények
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die('Nincs jogosultságod ehhez a művelethez.');
    }
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// --------------------------------------------------------------------
// Repository autoloading
//
// To avoid including individual repository classes everywhere, we use
// PHP's SPL autoloader. Any class located in the "repositories" folder
// (relative to this config.php) will be automatically included when
// referenced. This makes it easy to add new repositories without
// modifying the configuration file each time.
spl_autoload_register(function ($class) {
    $path = __DIR__ . '/repositories/' . $class . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

// ----------------------------------------
// Cache helper betöltése
// A cache_helper.php definiálja a cache_get és cache_set függvényeket.
// Ezekkel egyszerű fájl alapú cache-t valósítunk meg a gyakran olvasott,
// ritkán változó adatokhoz (pl. megyék, települések listája).
// A fájl a config.php mellett, a CRM könyvtárban található.
require_once __DIR__ . '/cache_helper.php';
?>
