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
Route::protect('auth');

// Instantiate repositories for notifications, approval notifications and chat operations.
/** @var NotificationRepository $notificationRepo */
$notificationRepo = new NotificationRepository($pdo);
/** @var ApprovalNotificationRepository $approvalNotifRepo */
$approvalNotifRepo = new ApprovalNotificationRepository($pdo);
/** @var ChatRepository $chatRepo */
$chatRepo = new ChatRepository($pdo);

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
 * az értesítések számában, jóváhagyási értesítésekben vagy új chat üzenetben,
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

// Segédváltozók a változások figyeléséhez
$lastPayloadHash = '';
$lastChatTime = date('Y-m-d H:i:s');

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

    // 1) Chat olvasatlan üzenetek száma
    $chatCount = $notificationRepo->getUnreadChatCount($userId);

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

    // 5) Legújabb chat üzenet toast-hoz
    $latestChatMessage = null;
    $newMsg = $notificationRepo->getLatestChatMessage($userId, $lastChatTime);
    if ($newMsg !== null) {
        // Rövidítsük az üzenetet a toastban való megjelenítéshez
        $msgText = $newMsg['message'];
        if (function_exists('mb_substr')) {
            $short = mb_substr($msgText, 0, 50);
            $suffix = mb_strlen($msgText) > 50 ? '...' : '';
        } else {
            $short = substr($msgText, 0, 50);
            $suffix = strlen($msgText) > 50 ? '...' : '';
        }
        $latestChatMessage = [
            'id' => (int)$newMsg['id'],
            'user_name' => $newMsg['user_name'],
            'message' => $short . $suffix,
            'created_at' => $newMsg['created_at'],
        ];
        // Frissítsük az utolsó csekk időt
        $lastChatTime = $newMsg['created_at'];
    }

    // Payload összeállítása
    $payload = [
        'chat_unread'        => (int)$chatCount,
        'approvals_pending'  => (int)$approvalCount,
        'new_clients_total'  => (int)$newClientsCount,
        'new_clients_by_county' => $newByCounty,
        'approval_notifications' => $approvalNotifications,
        'latest_chat_message'   => $latestChatMessage,
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