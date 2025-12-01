<?php
require_once 'config.php';
requireLogin();

// Megyék lekérdezése ügyfélszámmal
$stmt = $pdo->query("
    SELECT c.*, COUNT(cl.id) as client_count
    FROM counties c
    LEFT JOIN clients cl ON c.id = cl.county_id AND cl.approved = 1 AND cl.closed_at IS NULL
    GROUP BY c.id
    ORDER BY c.name
");
$counties = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #e3f2fd 0%, #e8eaf6 100%);
            min-height: 100vh;
        }
        .notification-badge {
            position: relative;
        }
        .notification-dot {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid white;
        }
        .notification-dot.chat {
            background: #ffc107;
        }
        .notification-dot.patchnotes {
            background: #28a745;
        }
        .county-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid #e0e0e0;
            box-shadow: 0 4px 8px rgba(0,0,0,0.12);
            background: white;
        }
        .county-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border-color: #0d6efd;
        }
        .header {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Mobilbarát stílusok */
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }
            
            .header {
                position: sticky;
                top: 0;
                z-index: 1000;
            }
            
            .header h3 {
                font-size: 1.1rem;
            }
            
            .header .d-flex {
                gap: 0.5rem !important;
            }
            
            .btn {
                min-height: 44px;
                min-width: 44px;
                font-size: 0.9rem;
            }
            
            .btn-sm {
                min-height: 44px;
                font-size: 0.85rem;
                padding: 0.4rem 0.6rem;
            }
            
            .county-card {
                margin-bottom: 1rem;
            }
            
            .county-card .card-body {
                padding: 1rem;
            }
            
            .county-card h5 {
                font-size: 1rem;
            }
            
            .county-card .fs-4 {
                font-size: 1.5rem !important;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 8px;
                padding-right: 8px;
                max-width: 100%;
            }
            
            .header .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .header h3 {
                font-size: 1rem;
            }
            
            .btn-sm span {
                font-size: 0.75rem;
            }
        }
        
        /* Fektetett mód (landscape) */
        @media (max-width: 896px) and (orientation: landscape) {
            .header {
                padding: 0.5rem 0 !important;
            }
            
            .header h3 {
                font-size: 1rem;
            }
            
            .btn-sm {
                padding: 0.3rem 0.5rem;
                min-height: 38px;
            }
            
            .container {
                max-width: 100%;
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .county-card .card-body {
                padding: 0.75rem;
            }
        }
    </style>
    <link rel="stylesheet" href="improved_styles.css?v=1.0">
</head>
<body>
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="mb-0"><?php echo APP_NAME; ?></h3>
                <div class="d-flex align-items-center gap-2 gap-md-3 flex-wrap justify-content-end">
                    <a href="chat.php" class="btn btn-outline-primary btn-sm notification-badge position-relative" title="Chat" id="chatLink">
                        <i class="bi bi-chat-dots-fill"></i> <span class="d-none d-md-inline">Chat</span>
                        <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" id="chatBadge" style="display: none; font-size: 0.7rem;">0</span>
                    </a>
                    <a href="patchnotes.php" class="btn btn-outline-success btn-sm notification-badge" title="Változásnapló" id="patchnotesLink">
                        <i class="bi bi-journal-text"></i> <span class="d-none d-md-inline">Változások</span>
                    </a>
                    <a href="profile.php" class="text-decoration-none text-dark">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <?php if (isAdmin()): ?>
                            <span class="badge bg-primary ms-2">Admin</span>
                        <?php endif; ?>
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="statistics.php" class="btn btn-outline-success btn-sm" title="Statisztikák">
                            <i class="bi bi-graph-up"></i> <span class="d-none d-md-inline">Statisztikák</span>
                        </a>
                        <a href="approvals.php" class="btn btn-outline-warning btn-sm position-relative" title="Jóváhagyások" id="approvalsLink">
                            <i class="bi bi-clock-history"></i> <span class="d-none d-md-inline">Jóváhagyások</span>
                            <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" id="approvalsBadge" style="display: none; font-size: 0.7rem;">0</span>
                        </a>
                        <a href="admin.php" class="btn btn-outline-primary btn-sm" title="Felhasználók">
                            <i class="bi bi-people-fill"></i> <span class="d-none d-md-inline">Felhasználók</span>
                        </a>
                    <?php else: ?>
                        <a href="my_requests.php" class="btn btn-outline-info btn-sm position-relative" title="Saját Kérések" id="myRequestsLink">
                            <i class="bi bi-file-earmark-text"></i> <span class="d-none d-md-inline">Saját Kérések</span>
                            <span class="badge bg-info rounded-pill position-absolute top-0 start-100 translate-middle" id="myRequestsBadge" style="display: none; font-size: 0.7rem;">0</span>
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm" title="Kijelentkezés">
                        <i class="bi bi-box-arrow-right"></i> <span class="d-none d-md-inline">Kijelentkezés</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="mb-4">
            <h2>Válassz megyét</h2>
            <p class="text-muted">Kattints egy megyére az ügyfelek megtekintéséhez és kezeléséhez</p>
        </div>

        <div class="row g-3">
            <?php foreach ($counties as $county): ?>
                <div class="col-md-4">
                    <a href="county.php?id=<?php echo $county['id']; ?>" class="text-decoration-none">
                        <div class="card county-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded p-3 me-3">
                                        <i class="bi bi-geo-alt-fill text-primary fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-0 text-dark"><?php echo escape($county['name']); ?></h5>
                                                <small class="text-muted">
                                                    <i class="bi bi-people-fill"></i> 
                                                    <?php echo $county['client_count']; ?> ügyfél
                                                </small>
                                            </div>
                                            <span class="badge bg-success new-client-badge" data-county-id="<?php echo $county['id']; ?>" style="display: none;">0 új</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Toast container -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <div id="toastContainer"></div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let lastChatCheck = new Date().toISOString();
        let shownPatchnotes = new Set(); // Trackáljuk a már megjelentített patchnote-okat
        
        // Toast értesítés megjelenítése
        function showToast(title, message, type = 'info', link = null) {
            const toastId = 'toast-' + Date.now();
            const bgClass = {
                'info': 'bg-primary',
                'success': 'bg-success',
                'warning': 'bg-warning',
                'danger': 'bg-danger'
            }[type] || 'bg-primary';
            
            const toastHTML = `
                <div class="toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header ${bgClass} text-white">
                        <i class="bi bi-bell-fill me-2"></i>
                        <strong class="me-auto">${title}</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body ${link ? 'cursor-pointer' : ''}" ${link ? `onclick="window.location.href='${link}'"` : ''}>
                        ${message}
                        ${link ? '<div class="mt-2"><small class="text-muted"><i class="bi bi-hand-index"></i> Kattints a megnyitáshoz</small></div>' : ''}
                    </div>
                </div>
            `;
            
            const container = document.getElementById('toastContainer');
            container.insertAdjacentHTML('beforeend', toastHTML);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
            toast.show();
            
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }
        
        // Értesítések ellenőrzése
        function checkNotifications() {
            fetch('notifications_api.php?action=get_counts')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Chat badge
                        const chatBadge = document.getElementById('chatBadge');
                        if (data.chat_unread > 0) {
                            chatBadge.textContent = data.chat_unread;
                            chatBadge.style.display = 'inline-block';
                        } else {
                            chatBadge.style.display = 'none';
                        }
                        
                        // Approvals badge (admin only)
                        const approvalsBadge = document.getElementById('approvalsBadge');
                        if (approvalsBadge && data.approvals_pending > 0) {
                            approvalsBadge.textContent = data.approvals_pending;
                            approvalsBadge.style.display = 'inline-block';
                        } else if (approvalsBadge) {
                            approvalsBadge.style.display = 'none';
                        }
                        
                        // New clients by county
                        data.new_clients_by_county.forEach(county => {
                            const badge = document.querySelector(`.new-client-badge[data-county-id="${county.county_id}"]`);
                            if (badge && county.new_count > 0) {
                                badge.textContent = county.new_count + ' új';
                                badge.style.display = 'inline-block';
                            }
                        });
                    }
                });
            
            // Legújabb chat üzenet ellenőrzése (toast-hoz)
            fetch(`notifications_api.php?action=get_latest_chat_message&last_check=${encodeURIComponent(lastChatCheck)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.has_new) {
                        showToast(
                            'Új üzenet',
                            `${data.message.user_name}: ${data.message.message}`,
                            'info',
                            'chat.php'
                        );
                        // Frissítjük a lastChatCheck-et az üzenet idéjére, hogy ne duplikáljon
                        lastChatCheck = data.message.created_at;
                    }
                });
            
            // Approval notifications (ügyintézőknek)
            const myRequestsBadge = document.getElementById('myRequestsBadge');
            if (myRequestsBadge) {
                fetch('approval_notifications_api.php?action=get_unread')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.count > 0) {
                            myRequestsBadge.textContent = data.count;
                            myRequestsBadge.style.display = 'inline-block';
                            
                            // Toast értesítések az új döntésekről
                            data.notifications.forEach(notif => {
                                if (notif.approval_status === 'approved') {
                                    showToast(
                                        'Ügyfél Elfogadva',
                                        `Az ügyfél "${notif.client_name}" jóváhagyásra került!`,
                                        'success',
                                        'my_requests.php'
                                    );
                                } else if (notif.approval_status === 'rejected') {
                                    showToast(
                                        'Ügyfél Elutasítva',
                                        `Az ügyfél "${notif.client_name}" elutasításra került. Indok: ${notif.rejection_reason}`,
                                        'danger',
                                        'my_requests.php'
                                    );
                                }
                                
                                // Olvasottnak jelöljük a toast megjelenítése után
                                fetch('approval_notifications_api.php?action=mark_read', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                    body: `notification_id=${notif.id}`
                                });
                            });
                        } else if (myRequestsBadge) {
                            myRequestsBadge.style.display = 'none';
                        }
                    });
            }
            
            // Patchnotes olvasatlan bejegyzések
            fetch('patchnotes_api.php?action=get_unread_count')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.unread_count > 0) {
                        const patchnotesLink = document.getElementById('patchnotesLink');
                        if (!patchnotesLink.querySelector('.notification-dot')) {
                            const dot = document.createElement('span');
                            dot.className = 'notification-dot patchnotes';
                            patchnotesLink.appendChild(dot);
                        }
                    }
                });
            
            // Legújabb major patchnote popup
            fetch('patchnotes_api.php?action=get_latest_unread')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.patchnote && !shownPatchnotes.has(data.patchnote.id)) {
                        shownPatchnotes.add(data.patchnote.id);
                        showPatchnotePopup(data.patchnote);
                    }
                });
        }
        
        // Patchnote popup megjelenítése
        function showPatchnotePopup(patchnote) {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-megaphone-fill"></i> Új frissítés: v${escapeHtml(patchnote.version)}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <h4>${escapeHtml(patchnote.title)}</h4>
                            <p style="white-space: pre-wrap;">${escapeHtml(patchnote.content)}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bezárás</button>
                            <a href="patchnotes.php" class="btn btn-success">Tovább a változásnaplóhoz</a>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Olvasottnak jelölés a bezáráskor
            modal.addEventListener('hidden.bs.modal', function() {
                fetch('patchnotes_api.php?action=mark_read&ids=' + patchnote.id);
                modal.remove();
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Oldal betöltésekor és 2 másodpercenként ellenőrzés
        checkNotifications();
        setInterval(checkNotifications, 2000);
    </script>
</body>
</html>
