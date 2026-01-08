<?php
/**
 * Server-Sent Events (SSE) endpoint for notifications.
 *
 * This script streams notification counts to connected clients,
 * reducing the need for frequent polling requests. It computes
 * the same counts as the `get_counts` action in notifications_api.php
 * and sends them whenever they change. Only logged-in users can
 * connect, and admin users receive pending approval counts.
 */

require_once 'config.php';

// SSE-compatible authentication check
// Instead of using Route::protect('auth') which sends HTML redirect,
// we manually check session and send SSE-formatted error if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    // Send SSE auth_error event (renamed from 'error' to avoid conflict with native onerror)
    echo "event: auth_error\n";
    echo "data: " . json_encode([
        'error' => 'unauthorized',
        'message' => 'Kérlek jelentkezz be!'
    ], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
    exit;
}

// Instantiate repositories for notifications and approval notifications.
/** @var NotificationRepository $notificationRepo */
$notificationRepo = new NotificationRepository($pdo);
/** @var ApprovalNotificationRepository $approvalNotifRepo */
$approvalNotifRepo = new ApprovalNotificationRepository($pdo);

// A session lezárása, hogy a hosszú SSE folyamat ne tartsa zárolva a PHP session-t.
// Így más kérések (pl. API hívások) párhuzamosan futhatnak.
session_write_close();
// Ha a kliens bezárja az oldalt, a script azonnal leállhat.
ignore_user_abort(true);

// Maximális futási idő másodpercben. Ha elérjük, kilépünk a ciklusból, így az SSE
// kapcsolat lezárul és a kliens újra tud kapcsolódni. Ezzel megelőzzük, hogy
// végtelen folyamatok gyűljenek fel a szerveren.
$startTime = time();
$maxDuration = SSE_MAX_DURATION; // 5 perc

/*
 * Server‑Sent Events (SSE) endpoint for CRM értesítések.
 *
 * Ez az endpoint folyamatosan figyeli az adatbázist, és amint változás történik
 * az értesítések számában vagy jóváhagyási értesítésekben,
 * push‑olja az adatokat a kliensnek. Így megszüntethető a gyakori polling,
 * és valósidejű frissítéseket kaphatnak a felhasználók. A stream bezárul,
 * ha a kliens megszakítja a kapcsolatot.
 */

// SSE fejléc beállítása
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Kimeneti puffer kikapcsolása
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

$userId = $_SESSION['user_id'];
$isAdmin = isAdmin();

// Segédváltozó a változások figyeléséhez
$lastPayloadHash = '';

while (true) {
    // Kapcsolat ellenőrzése: ha a kliens bontja, kilépünk a ciklusból
    if (connection_aborted()) {
        break;
    }

    // Ha a maximális futási idő letelt, lezárjuk a ciklust, hogy a kapcsolat
    // automatikusan újracsatlakozhasson a kliens oldalon. Ezzel elkerüljük a
    // végtelenül futó folyamatokat, amelyek felhalmozódhatnak a szerveren.
    if (time() - $startTime >= $maxDuration) {
        break;
    }



    // 2) Függő jóváhagyások száma (csak adminoknak)
    $approvalCount = $isAdmin ? $notificationRepo->getPendingApprovalsCount() : 0;

    // 3) Új ügyfelek megyénként és összesen
    $newByCounty = $notificationRepo->getNewClientsByCounty($userId, $isAdmin);
    $newClientsCount = $notificationRepo->getNewClientsTotal($userId, $isAdmin);

    // 4) Jóváhagyási értesítések (ügyintézőknek)
    $approvalNotifications = $approvalNotifRepo->getUnreadByUser($userId);
    if (!empty($approvalNotifications)) {
        // Olvasottnak jelöljük a kiküldött értesítéseket
        foreach ($approvalNotifications as $notif) {
            $approvalNotifRepo->markRead($notif['id'], $userId);
        }
    }

    // Payload összeállítása
    $payload = [
        'approvals_pending' => (int) $approvalCount,
        'new_clients_total' => (int) $newClientsCount,
        'new_clients_by_county' => $newByCounty,
        'approval_notifications' => $approvalNotifications,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $hash = md5($json);

    // Csak akkor küldünk eseményt, ha változott valami
    if ($hash !== $lastPayloadHash) {
        echo "data: {$json}\n\n";
        @flush();
        $lastPayloadHash = $hash;
    }

    // Kis szünet, hogy ne terheljük túl az adatbázist
    // A korábbi 3 másodperces várakozást 5 másodpercre növeltük,
    // hogy csökkentsük a lekérdezések gyakoriságát és ezzel az erőforrás‑felhasználást.
    sleep(SSE_POLL_INTERVAL);
}
?>