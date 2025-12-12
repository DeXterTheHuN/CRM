<?php
/**
 * Server‑Sent Events (SSE) végpont a chat üzenetek valós idejű továbbításához.
 *
 * A kliens a last_id paraméterrel jelzi, melyik az utolsó ismert üzenet azonosítója.
 * A végpont ezután folyamatosan figyeli az adatbázist, és amint új üzenet érkezik,
 * elküldi a kliensnek. Ha a kliens megszakítja a kapcsolatot, a ciklus kilép.
 */

require_once 'config.php';
Route::protect('auth');

// Use ChatRepository for fetching messages instead of direct SQL.
/** @var ChatRepository $chatRepo */
$chatRepo = new ChatRepository($pdo);

// A session-t lezárjuk, hogy az SSE kapcsolat ne tartsa zárolva a munkamenetet.
session_write_close();
// A kliens kapcsolatának megszakítása esetén a script leállhat.
ignore_user_abort(true);

// Maximális futási idő (másodpercben). Ha letelik, a script befejezi a futást.
$startTime = time();
$maxDuration = SSE_MAX_DURATION; // 5 perc

// Beérkező paraméter: utolsó ismert üzenet ID-je
$lastId = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

// SSE fejléc beállítása
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Kimeneti puffer kikapcsolása
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

// Végtelen ciklus a streameléshez
while (true) {
    // Ha a kliens bontja a kapcsolatot, kilépünk
    if (connection_aborted()) {
        break;
    }

    // Ha a maximális futási idő letelt, lezárjuk a streamet, hogy az SSE kliens
    // újrakapcsolódhasson és ne fusson végtelenül a háttérben.
    if (time() - $startTime >= $maxDuration) {
        break;
    }
    // Új üzenetek lekérdezése az utolsó ismert ID után a repository segítségével
    $newMessages = $chatRepo->getMessages($lastId, 1000);

    if (!empty($newMessages)) {
        // Frissítjük a lastId-t az utolsó üzenet ID-jére
        $lastRow = end($newMessages);
        $lastId = (int)$lastRow['id'];

        // Kliensnek küldendő payload
        $payload = ['messages' => $newMessages];
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
        @flush();
    }
    // Várunk kicsit, hogy ne terheljük a szervert. A korábbi 2 másodperces
    // várakozás helyett 5 másodpercet használunk, így kevesebb lekérdezés
    // történik, ami csökkenti az adatbázis és a processzor terhelését.
    sleep(SSE_POLL_INTERVAL);
}
?>