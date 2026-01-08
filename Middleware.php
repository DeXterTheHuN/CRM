<?php
/**
 * Middleware System
 * 
 * Egyszerű middleware rendszer az autentikáció és autorizáció kezelésére.
 * Bár nem egy teljes routing-alapú middleware stack (mint Laravel),
 * de centralizálja és egyszerűsíti a jogosultság-kezelést.
 */

class Middleware
{
    /**
     * Registered middleware instances
     */
    private static array $middlewares = [];

    /**
     * Register a middleware
     */
    public static function register(string $name, callable $middleware): void
    {
        self::$middlewares[$name] = $middleware;
    }

    /**
     * Run middleware(s)
     * 
     * @param string|array $middlewares Middleware name(s) to run
     */
    public static function run($middlewares): void
    {
        $middlewares = is_array($middlewares) ? $middlewares : [$middlewares];

        foreach ($middlewares as $name) {
            if (!isset(self::$middlewares[$name])) {
                throw new Exception("Middleware '{$name}' not found");
            }

            $middleware = self::$middlewares[$name];
            $middleware();
        }
    }
}

/**
 * Built-in Middlewares
 */
class AuthMiddleware
{
    /**
     * Initialize built-in auth middlewares
     */
    public static function init(): void
    {
        // Auth middleware - require login
        Middleware::register('auth', function () {
            if (!isset($_SESSION['user_id'])) {
                $_SESSION['error'] = 'Kérlek jelentkezz be!';
                header('Location: login.php');
                exit;
            }
        });

        // Admin middleware - require admin role
        Middleware::register('admin', function () {
            // Először ellenőrizzük hogy be van-e jelentkezve
            if (!isset($_SESSION['user_id'])) {
                $_SESSION['error'] = 'Kérlek jelentkezz be!';
                header('Location: login.php');
                exit;
            }

            // Aztán ellenőrizzük az admin jogot
            if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                ErrorHandler::logAppError('Unauthorized admin access attempt', [
                    'user_id' => $_SESSION['user_id'],
                    'username' => $_SESSION['name'] ?? 'unknown',
                    'role' => $_SESSION['role'] ?? 'none',
                    'url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);

                $_SESSION['error'] = 'Nincs jogosultságod ehhez a művelethez.';
                header('Location: index.php');
                exit;
            }
        });

        // Guest middleware - csak nem bejelentkezett usereknek
        Middleware::register('guest', function () {
            if (isset($_SESSION['user_id'])) {
                header('Location: index.php');
                exit;
            }
        });

        // Verified middleware - csak jóváhagyott usereknek
        Middleware::register('verified', function () {
            if (!isset($_SESSION['user_id'])) {
                $_SESSION['error'] = 'Kérlek jelentkezz be!';
                header('Location: login.php');
                exit;
            }

            // Check approval status - using approval_status enum
            if (empty($_SESSION['approval_status']) || $_SESSION['approval_status'] !== 'approved') {
                $_SESSION['error'] = 'A fiókod még nincs jóváhagyva. Kérlek várj az adminisztrátor jóváhagyására.';
                header('Location: logout.php');
                exit;
            }
        });
    }
}

/**
 * Route Protection Helper
 * 
 * Egyszerűsített "route" védelem middleware-rel
 */
class Route
{
    /**
     * Protect current route with middleware(s)
     * 
     * @param string|array $middlewares Middleware name(s)
     * 
     * Példa használat:
     * Route::protect('auth'); // Csak bejelentkezve
     * Route::protect('admin'); // Csak admin (implicit auth is)
     * Route::protect(['auth', 'verified']); // Több middleware
     */
    public static function protect($middlewares): void
    {
        Middleware::run($middlewares);
    }
}

/**
 * API Route Helper
 * 
 * Speciális middleware API endpoint-okhoz
 */
class ApiRoute
{
    /**
     * Protect API route
     * 
     * Ha a middleware fail, JSON error-t ad vissza exit-tel
     */
    public static function protect($middlewares): void
    {
        $middlewares = is_array($middlewares) ? $middlewares : [$middlewares];

        try {
            foreach ($middlewares as $name) {
                if ($name === 'auth') {
                    if (!isset($_SESSION['user_id'])) {
                        ApiResponse::unauthorized('Kérlek jelentkezz be');
                    }
                } elseif ($name === 'admin') {
                    if (!isset($_SESSION['user_id'])) {
                        ApiResponse::unauthorized('Kérlek jelentkezz be');
                    }
                    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                        ErrorHandler::logAppError('Unauthorized API admin access', [
                            'user_id' => $_SESSION['user_id'],
                            'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                        ]);
                        ApiResponse::unauthorized('Nincs jogosultságod ehhez a művelethez');
                    }
                }
            }
        } catch (Exception $e) {
            ApiResponse::error('Authentication failed', $e->getMessage(), 401);
        }
    }
}

// Inicializáljuk a beépített middleware-eket
AuthMiddleware::init();
