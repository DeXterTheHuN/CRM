<?php
require_once 'config.php';
ApiRoute::protect('auth');

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Instantiate PatchnotesRepository
$patchRepo = new PatchnotesRepository($pdo);

try {
    switch ($action) {
        case 'add_patchnote':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'error' => 'Nincs jogosultságod']);
                exit;
            }
            $version = trim($_POST['version'] ?? '');
            $title   = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $is_major = isset($_POST['is_major']) ? (int)$_POST['is_major'] : 0;
            if ($version === '' || $title === '' || $content === '') {
                echo json_encode(['success' => false, 'error' => 'Minden mező kitöltése kötelező']);
                exit;
            }
            $newId = $patchRepo->addPatchnote($version, $title, $content, $user_name, $is_major);
            echo json_encode(['success' => true, 'patchnote_id' => $newId]);
            break;
        case 'delete_patchnote':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'error' => 'Nincs jogosultságod']);
                exit;
            }
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $patchRepo->deletePatchnote($id);
            }
            echo json_encode(['success' => true]);
            break;
        case 'mark_read':
            $ids = $_GET['ids'] ?? '';
            $id_array = array_filter(array_map('intval', explode(',', $ids)));
            $patchRepo->markRead($user_id, $id_array);
            echo json_encode(['success' => true]);
            break;
        case 'get_unread_count':
            $unreadCount = $patchRepo->getUnreadCount($user_id);
            echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
            break;
        case 'get_latest_unread':
            $latest = $patchRepo->getLatestUnreadMajor($user_id);
            echo json_encode(['success' => true, 'patchnote' => $latest ?: null]);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Érvénytelen művelet']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
