// CRM Main Scripts - index.php

let lastChatCheck = new Date().toISOString();
let shownPatchnotes = new Set();

// Toast értesítés megjelenítése
function showToast(title, message, type = 'info', link = null) {
    const toastId = 'toast-' + Date.now();
    const bgClass = {
        'info': 'bg-primary',
        'success': 'bg-success',
        'warning': 'bg-warning',
        'danger': 'bg-danger'
    }[type] || 'bg-primary';

    // Biztosítjuk, hogy legyen toastContainer a DOM-ban; ha nincs, létrehozzuk
    let container = document.getElementById('toastContainer');
    if (!container) {
        // létrehozunk egy wrapper div-et hasonló elhelyezéssel, mint az index oldalon
        const wrapper = document.createElement('div');
        wrapper.className = 'position-fixed top-0 end-0 p-3';
        wrapper.style.zIndex = '9999';
        wrapper.innerHTML = '<div id="toastContainer"></div>';
        document.body.appendChild(wrapper);
        container = document.getElementById('toastContainer');
    }
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

    container.insertAdjacentHTML('beforeend', toastHTML);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
    toast.show();

    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Értesítések ellenőrzése
// A skipCounts paraméterrel szabályozható, hogy az első két lekérdezést (get_counts és get_latest_chat_message)
// kihagyjuk-e. SSE használata esetén ezek a lekérdezések feleslegesek.
function checkNotifications(skipCounts = false) {
    if (!skipCounts) {
        // Csak akkor kérjük le a számlálókat, ha nem használjuk SSE-t vagy kezdeti betöltéskor
        fetch('notifications_api.php?action=get_counts')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationsUI(data);
                }
            })
            .catch(error => {
                console.error('Notifications count fetch error:', error);
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
                    lastChatCheck = data.message.created_at;
                }
            })
            .catch(error => {
                console.error('Latest chat message fetch error:', error);
            });
    }

    // Approval notifications (ügyintézőknek)
    const myRequestsBadge = document.getElementById('myRequestsBadge');
    if (myRequestsBadge) {
        fetch('approval_notifications_api.php?action=get_unread')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.count > 0) {
                    myRequestsBadge.textContent = data.count;
                    myRequestsBadge.style.display = 'inline-block';

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

                        fetch('approval_notifications_api.php?action=mark_read', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: `notification_id=${notif.id}`
                        }).catch(error => {
                            console.error('Mark notification read error:', error);
                        });
                    });
                } else if (myRequestsBadge) {
                    myRequestsBadge.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Approval notifications fetch error:', error);
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
        })
        .catch(error => {
            console.error('Patchnotes unread count fetch error:', error);
        });

    // Legújabb major patchnote popup
    fetch('patchnotes_api.php?action=get_latest_unread')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.patchnote && !shownPatchnotes.has(data.patchnote.id)) {
                shownPatchnotes.add(data.patchnote.id);
                showPatchnotePopup(data.patchnote);
            }
        })
        .catch(error => {
            console.error('Latest patchnote fetch error:', error);
        });
}

// SSE értesítések UI frissítése
function updateNotificationsUI(data) {
    // Chat badge frissítés
    const chatBadge = document.getElementById('chatBadge');
    if (chatBadge) {
        if (data.chat_unread > 0) {
            chatBadge.textContent = data.chat_unread;
            chatBadge.style.display = 'inline-block';
        } else {
            chatBadge.style.display = 'none';
        }
    }

    // Approvals badge frissítés (csak adminnál létezik)
    const approvalsBadge = document.getElementById('approvalsBadge');
    if (approvalsBadge) {
        if (data.approvals_pending > 0) {
            approvalsBadge.textContent = data.approvals_pending;
            approvalsBadge.style.display = 'inline-block';
        } else {
            approvalsBadge.style.display = 'none';
        }
    }

    // Új ügyfelek megyei jelzők frissítése
    if (Array.isArray(data.new_clients_by_county)) {
        data.new_clients_by_county.forEach(county => {
            const badge = document.querySelector(`.new-client-badge[data-county-id="${county.county_id}"]`);
            if (badge) {
                if (county.new_count > 0) {
                    badge.textContent = county.new_count + ' új';
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        });
    }
}

// SSE értesítések feldolgozása.
// A kapott adatban a számlálók mellett jóváhagyási értesítések és legújabb
// chat üzenet is lehet, melyeket toast üzenetek formájában jelenítünk meg.
function handleSSEData(data) {
    // Frissítjük a badge-eket és a county jelzőket
    updateNotificationsUI(data);

    // Jóváhagyási értesítések (ügyintézők)
    if (Array.isArray(data.approval_notifications) && data.approval_notifications.length > 0) {
        const myRequestsBadge = document.getElementById('myRequestsBadge');
        if (myRequestsBadge) {
            myRequestsBadge.textContent = data.approval_notifications.length;
            myRequestsBadge.style.display = 'inline-block';
        }
        data.approval_notifications.forEach(notif => {
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
        });
    }

    // Legújabb chat üzenet toast
    if (data.latest_chat_message && data.latest_chat_message.created_at) {
        showToast(
            'Új üzenet',
            `${data.latest_chat_message.user_name}: ${data.latest_chat_message.message}`,
            'info',
            'chat.php'
        );
        // Frissítjük a lastChatCheck változót a következő polling ellenőrzéshez
        lastChatCheck = data.latest_chat_message.created_at;
    }
}

// SSE inicializálása
let notificationsEventSource = null; // Global EventSource tracker

function initNotificationsSSE() {
    if (typeof EventSource === 'undefined') {
        // A böngésző nem támogatja az SSE-t, marad a polling fallback
        setInterval(checkNotifications, 3000);
        return;
    }
    
    // ✅ CLEANUP: Close existing connection before creating new one
    if (notificationsEventSource) {
        notificationsEventSource.close();
        notificationsEventSource = null;
    }
    
    notificationsEventSource = new EventSource('notifications_sse.php');
    
    notificationsEventSource.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            handleSSEData(data);
        } catch (e) {
            console.error('SSE adat hiba:', e);
        }
    };
    
    notificationsEventSource.onerror = function() {
        console.error('SSE kapcsolat hiba. Újracsatlakozás néhány másodperc múlva.');
        
        // ✅ CLEANUP: Properly close the failed connection
        if (notificationsEventSource) {
            notificationsEventSource.close();
            notificationsEventSource = null;
        }
        
        // Pár másodperces várakozás után újraindítjuk az SSE kapcsolatot.
        setTimeout(initNotificationsSSE, 5000);
    };
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

// Inicializálás
document.addEventListener('DOMContentLoaded', function() {
    // Kezdeti ellenőrzés: teljes lekérdezés (counts + egyéb)
    checkNotifications(false);
    // SSE inicializálása a folyamatos értesítésekhez
    initNotificationsSSE();
    // Patchnote és approval notification frissítés 30 másodpercenként, counts nélkül
    setInterval(function() {
        checkNotifications(true);
    }, 30000);
});
