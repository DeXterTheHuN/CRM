<?php
/**
 * Központi Error Handler és Logger
 * 
 * Ez az osztály biztosítja az egységes hibakezelést és naplózást az alkalmazásban.
 * Célja:
 * - Egységes hibakezelés
 * - Biztonsági információk elrejtése a felhasználók elől
 * - Részletes hibainformációk naplózása fejlesztőknek
 * - Különböző típusú hibák megfelelő kezelése
 */

class ErrorHandler
{
    /**
     * Error log fájl elérési útja
     */
    private const ERROR_LOG_FILE = __DIR__ . '/logs/app_errors.log';

    /**
     * Debug mód (fejlesztés során true, production-ben false)
     */
    private static bool $debugMode = false;

    /**
     * PDO connection for database logging
     */
    private static ?PDO $pdo = null;

    /**
     * Inicializálja az error handler-t
     */
    public static function init(bool $debugMode = false, ?PDO $pdo = null): void
    {
        self::$debugMode = $debugMode;
        self::$pdo = $pdo;

        // Saját error handler regisztrálása
        set_error_handler([self::class, 'handleError']);

        // Saját exception handler regisztrálása
        set_exception_handler([self::class, 'handleException']);

        // Shutdown function a fatal error-okhoz
        register_shutdown_function([self::class, 'handleFatalError']);

        // Logs könyvtár létrehozása, ha nem létezik
        $logDir = dirname(self::ERROR_LOG_FILE);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
    }

    /**
     * PHP error handler
     */
    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile = '',
        int $errline = 0
    ): bool {
        // Ellenőrizzük, hogy az error reporting be van-e kapcsolva erre a hibaszintre
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorType = self::getErrorType($errno);

        // Naplózás
        self::logError($errorType, $errstr, $errfile, $errline);

        // Debug módban megjelenítjük a hibát
        if (self::$debugMode) {
            echo "<div style='background:#ffebee;color:#c62828;padding:10px;margin:10px 0;border-left:4px solid #c62828;'>";
            echo "<strong>[$errorType]</strong> $errstr<br>";
            echo "<small>File: $errfile (Line: $errline)</small>";
            echo "</div>";
        }

        // Ne futtassuk a PHP beépített error handler-ét
        return true;
    }

    /**
     * Exception handler
     */
    public static function handleException(Throwable $exception): void
    {
        // Naplózás
        self::logException($exception);

        // HTTP fejléc beállítása
        if (!headers_sent()) {
            http_response_code(500);
        }

        // Debug módban részletes hiba
        if (self::$debugMode) {
            echo "<div style='background:#ffebee;color:#c62828;padding:20px;margin:20px;border:2px solid #c62828;'>";
            echo "<h3>🔴 Exception: " . get_class($exception) . "</h3>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . $exception->getFile() . " (Line: " . $exception->getLine() . ")</p>";
            echo "<pre style='background:#fff;padding:10px;overflow:auto;'>" .
                htmlspecialchars($exception->getTraceAsString()) . "</pre>";
            echo "</div>";
        } else {
            // Production módban általános hibaüzenet
            echo self::getGenericErrorPage();
        }

        exit(1);
    }

    /**
     * Fatal error handler
     */
    public static function handleFatalError(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::logError(
                self::getErrorType($error['type']),
                $error['message'],
                $error['file'],
                $error['line']
            );

            if (!self::$debugMode && !headers_sent()) {
                echo self::getGenericErrorPage();
            }
        }
    }

    /**
     * Exception naplózása
     */
    private static function logException(Throwable $exception): void
    {
        $message = sprintf(
            "[%s] Exception: %s\nMessage: %s\nFile: %s (Line: %d)\nTrace:\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
            str_repeat('-', 80)
        );

        // File logging
        error_log($message, 3, self::ERROR_LOG_FILE);

        // Database logging
        self::logToDatabase([
            'error_type' => 'EXCEPTION',
            'severity' => 'FATAL',
            'message' => get_class($exception) . ': ' . $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Error naplózása
     */
    private static function logError(
        string $type,
        string $message,
        string $file,
        int $line
    ): void {
        $logMessage = sprintf(
            "[%s] %s: %s in %s (Line: %d)\n",
            date('Y-m-d H:i:s'),
            $type,
            $message,
            $file,
            $line
        );

        // File logging
        error_log($logMessage, 3, self::ERROR_LOG_FILE);

        // Database logging
        $severity = match ($type) {
            'FATAL ERROR' => 'FATAL',
            'WARNING' => 'WARNING',
            'NOTICE' => 'NOTICE',
            'DEPRECATED' => 'WARNING',
            default => 'ERROR'
        };

        self::logToDatabase([
            'error_type' => 'PHP_ERROR',
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line
        ]);
    }

    /**
     * Egyedi alkalmazás hiba naplózása
     */
    public static function logAppError(string $message, array $context = []): void
    {
        $logMessage = sprintf(
            "[%s] APP_ERROR: %s\n",
            date('Y-m-d H:i:s'),
            $message
        );

        if (!empty($context)) {
            $logMessage .= "Context: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
        }

        $logMessage .= str_repeat('-', 80) . "\n";

        // File logging
        error_log($logMessage, 3, self::ERROR_LOG_FILE);

        // Database logging
        self::logToDatabase([
            'error_type' => 'APP_ERROR',
            'severity' => 'ERROR',
            'message' => $message,
            'context' => $context
        ]);
    }

    /**
     * Error típus szövegének lekérése
     */
    private static function getErrorType(int $errno): string
    {
        return match ($errno) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'FATAL ERROR',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'WARNING',
            E_NOTICE, E_USER_NOTICE => 'NOTICE',
            E_STRICT => 'STRICT',
            E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
            default => 'UNKNOWN ERROR'
        };
    }

    /**
     * Log to database
     */
    private static function logToDatabase(array $errorData): void
    {
        if (self::$pdo === null) {
            return; // No database connection available
        }

        try {
            // Add request context
            $errorData['url'] = $_SERVER['REQUEST_URI'] ?? null;
            $errorData['method'] = $_SERVER['REQUEST_METHOD'] ?? null;
            $errorData['user_id'] = $_SESSION['user_id'] ?? null;
            $errorData['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $errorData['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;

            $sql = "INSERT INTO error_logs 
                    (error_type, severity, message, file, line, trace, url, method, user_id, user_agent, ip_address, context)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = self::$pdo->prepare($sql);
            $stmt->execute([
                $errorData['error_type'] ?? 'UNKNOWN',
                $errorData['severity'] ?? 'ERROR',
                $errorData['message'] ?? '',
                $errorData['file'] ?? null,
                $errorData['line'] ?? null,
                $errorData['trace'] ?? null,
                $errorData['url'],
                $errorData['method'],
                $errorData['user_id'],
                $errorData['user_agent'],
                $errorData['ip_address'],
                isset($errorData['context']) ? json_encode($errorData['context'], JSON_UNESCAPED_UNICODE) : null
            ]);
        } catch (Exception $e) {
            // Fallback - log the database error to file
            error_log("[" . date('Y-m-d H:i:s') . "] Failed to log error to database: " . $e->getMessage() . "\n", 3, self::ERROR_LOG_FILE);
        }
    }

    /**
     * Általános hibaoldal HTML
     */
    private static function getGenericErrorPage(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hiba történt</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-container {
            background: white;
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 32px;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .btn {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>Hiba történt</h1>
        <p>Sajnáljuk, de valami hiba történt a kérés feldolgozása során.</p>
        <p>Kérjük, próbáld meg később, vagy lépj kapcsolatba az ügyfélszolgálattal, ha a probléma továbbra is fennáll.</p>
        <a href="/" class="btn">Vissza a főoldalra</a>
    </div>
</body>
</html>
HTML;
    }
}

/**
 * API Response Helper
 * 
 * Segédfüggvények API válaszok egységes kezeléséhez
 */
class ApiResponse
{
    /**
     * Sikeres JSON válasz
     */
    public static function success(mixed $data = null, string $message = ''): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $response = ['success' => true];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Hiba JSON válasz
     * 
     * @param string $userMessage Felhasználónak szánt biztonságos üzenet
     * @param string|null $internalMessage Belső hibaüzenet naplózáshoz
     * @param int $httpCode HTTP status kód
     */
    public static function error(
        string $userMessage,
        ?string $internalMessage = null,
        int $httpCode = 400,
        array $context = []
    ): void {
        // Belső hiba naplózása, ha van
        if ($internalMessage !== null) {
            ErrorHandler::logAppError($internalMessage, $context);
        }

        // HTTP status kód beállítása
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'success' => false,
            'error' => $userMessage
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Validációs hiba válasz
     */
    public static function validationError(array $errors): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'error' => 'Validációs hiba',
            'errors' => $errors
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Nem engedélyezett művelet
     */
    public static function unauthorized(string $message = 'Nincs jogosultságod ehhez a művelethez'): void
    {
        self::error($message, 'Unauthorized access attempt', 403);
    }

    /**
     * Nem található erőforrás
     */
    public static function notFound(string $message = 'A kért erőforrás nem található'): void
    {
        self::error($message, 'Resource not found', 404);
    }
}
