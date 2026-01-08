// CRM Main Scripts - index.php

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

/**
 * Hungarian Phone Number Formatter
 * Formats phone numbers to: +36 XX XXX XXXX
 * Prevents +36 duplication if user types it
 */
function formatPhoneNumber(input) {
    if (!input) return;

    let value = input.value;

    // Remove all non-digits except +
    let cleanValue = value.replace(/[^\d+]/g, '');

    // Remove all + signs for processing
    let digits = cleanValue.replace(/\+/g, '');

    // If starts with 06, convert to 36
    if (digits.startsWith('06')) {
        digits = '36' + digits.substring(2);
    }

    // If doesn't start with 36, prepend it
    if (!digits.startsWith('36')) {
        digits = '36' + digits;
    }

    // Limit to 11 digits (36 + 9 digits)
    digits = digits.substring(0, 11);

    // Only format if we have at least 3 digits (36 + at least 1 more)
    // This prevents premature formatting when user types "+3" or "+36"
    if (digits.length < 3) {
        // Just return the raw input to let user continue typing
        input.value = value;
        return;
    }

    // Format: +36 XX XXX XXXX
    let formatted = '+36';

    if (digits.length > 2) {
        formatted += ' ' + digits.substring(2, 4);
    }
    if (digits.length > 4) {
        formatted += ' ' + digits.substring(4, 7);
    }
    if (digits.length > 7) {
        formatted += ' ' + digits.substring(7, 11);
    }

    input.value = formatted.trim();
}

function validatePhoneNumber(phone) {
    if (!phone || phone.trim() === '') {
        return true; // Empty is valid (optional field)
    }

    const pattern = /^\+36 [0-9]{2} [0-9]{3} [0-9]{4}$/;
    return pattern.test(phone);
}

function attachPhoneFormatter(inputElement) {
    if (!inputElement) return;

    // Format only on blur (when user leaves the field)
    inputElement.addEventListener('blur', function () {
        formatPhoneNumber(this);
    });

    // Format on load if value exists
    if (inputElement.value) {
        formatPhoneNumber(inputElement);
    }
}

// Értesítések ellenőrzése
// A skipCounts paraméterrel szabályozható, hogy a get_counts lekérdezést
// kihagyjuk-e. SSE használata esetén ez a lekérdezés felesleges.
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
            .catch(() => { }); // Silent error handling - old working pattern
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

                    // Show toasts for each notification
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
                    });

                    // Mark ALL as read with ONE API call (not individual calls per notification!)
                    // This prevents 508 errors from multiple parallel requests
                    fetch('approval_notifications_api.php?action=mark_all_read', {
                        method: 'POST'
                    }).catch(() => { }); // Silent
                } else if (myRequestsBadge) {
                    myRequestsBadge.style.display = 'none';
                }
            })
            .catch(() => { }); // Silent error handling
    }
}


// SSE értesítések UI frissítése
function updateNotificationsUI(data) {
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
// A kapott adatban a számlálók mellett jóváhagyási értesítések is lehetnek,
// melyeket toast üzenetek formájában jelenítünk meg.
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
}

// SSE inicializálása
let notificationsEventSource = null; // Global EventSource tracker
let sseRetryCount = 0; // Retry counter to prevent infinite reconnection loop
const SSE_MAX_RETRIES = 3; // Maximum reconnection attempts before giving up (reduced from 5)
const SSE_RETRY_DELAY = 60000; // 60 seconds between reconnection attempts (increased from 30s)

function initNotificationsSSE() {
    if (typeof EventSource === 'undefined') {
        // A böngésző nem támogatja az SSE-t, marad a polling fallback
        // 30 másodperc a rate limiting elkerülése érdekében
        setInterval(checkNotifications, 30000);
        return;
    }

    // CLEANUP: Close existing connection before creating new one
    if (notificationsEventSource) {
        notificationsEventSource.close();
        notificationsEventSource = null;
    }

    notificationsEventSource = new EventSource('notifications_sse.php');

    notificationsEventSource.onmessage = function (event) {
        try {
            const data = JSON.parse(event.data);
            handleSSEData(data);
        } catch (e) {
            console.error('SSE adat hiba:', e);
        }
    };

    // Handle custom auth_error events (e.g., authentication failures)
    notificationsEventSource.addEventListener('auth_error', function (event) {
        try {
            const errorData = JSON.parse(event.data);
            if (errorData.error === 'unauthorized') {
                console.warn('SSE Authentication error:', errorData.message);
                // Close connection gracefully
                if (notificationsEventSource) {
                    notificationsEventSource.close();
                    notificationsEventSource = null;
                }
                // Redirect to login page
                window.location.href = 'login.php';
                return;
            }
        } catch (e) {
            console.error('Error parsing auth_error event:', e);
        }
    });

    notificationsEventSource.onerror = function () {
        // CLEANUP: Properly close the failed connection
        if (notificationsEventSource) {
            notificationsEventSource.close();
            notificationsEventSource = null;
        }

        // Limit reconnection attempts to prevent infinite loop (mobile issue)
        sseRetryCount++;
        if (sseRetryCount >= SSE_MAX_RETRIES) {
            console.warn('SSE max retries reached, switching to polling fallback');
            setInterval(checkNotifications, 30000);
            return;
        }

        // 30 seconds delay before reconnecting to prevent server overload
        setTimeout(initNotificationsSSE, SSE_RETRY_DELAY);
    };

    // Reset retry counter on successful connection
    notificationsEventSource.onopen = function () {
        sseRetryCount = 0;
    };
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Inicializálás
document.addEventListener('DOMContentLoaded', function () {
    // Kezdeti ellenőrzés: teljes lekérdezés (counts + egyéb)
    checkNotifications(false);
    // SSE inicializálása a folyamatos értesítésekhez
    // Az SSE vagy a fallback polling gondoskodik a frissítésekről,
    // nincs szükség extra setInterval hívásra!
    initNotificationsSSE();
});
