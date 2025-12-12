// Chat Scripts - chat.php
// userId és userName változókat a PHP adja át

let lastMessageId = 0;
let isLoadingMessages = false;
let chatEventSource = null;

// Üzenetek betöltése
function loadMessages() {
    if (isLoadingMessages) return;
    isLoadingMessages = true;

    fetch('chat_api.php?action=get_messages&last_id=' + lastMessageId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                const chatMessages = document.getElementById('chatMessages');
                const wasAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;

                data.messages.forEach(msg => {
                    const msgId = parseInt(msg.id);
                    if (msgId > lastMessageId) {
                        lastMessageId = msgId;
                        appendMessage(msg);
                    }
                });

                if (wasAtBottom) {
                    scrollToBottom();
                }

                markAsRead();
            }
        })
        .catch(error => {
            console.error('Hiba az üzenetek betöltésekor:', error);
        })
        .finally(() => {
            isLoadingMessages = false;
        });
}

// Üzenet hozzáadása a chat-hez
function appendMessage(msg) {
    const chatMessages = document.getElementById('chatMessages');

    if (chatMessages.querySelector('.text-center')) {
        chatMessages.innerHTML = '';
    }

    const isOwn = parseInt(msg.user_id) === parseInt(userId);
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message ' + (isOwn ? 'own' : 'other');

    const time = new Date(msg.created_at).toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit' });

    messageDiv.innerHTML = `
        ${!isOwn ? `<div class="message-sender">${escapeHtml(msg.user_name)}</div>` : ''}
        <div class="message-bubble">
            ${escapeHtml(msg.message)}
            <div class="message-time">${time}</div>
        </div>
    `;

    chatMessages.appendChild(messageDiv);
}

// Üzenet küldése
function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();

    if (!message) return;

    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('message', message);

    fetch('chat_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            input.style.height = 'auto';
            loadMessages();
        } else {
            alert('Hiba az üzenet küldésekor: ' + (data.error || 'Ismeretlen hiba'));
        }
    })
    .catch(error => {
        console.error('Üzenet küldési hiba:', error);
        alert('Hiba az üzenet küldésekor. Kérlek próbáld újra!');
    });
}

// Görgetés az aljára
function scrollToBottom() {
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Üzenetek olvasottnak jelölése
function markAsRead() {
    if (lastMessageId > 0) {
        fetch('chat_api.php?action=mark_read&last_id=' + lastMessageId)
            .catch(error => {
                console.error('Mark as read error:', error);
            });
    }
}

// HTML escape
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Inicializálás
document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('messageInput');
    
    // Auto-resize textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    loadMessages();
    // SSE kapcsolat inicializálása üzenetekhez. Ha a böngésző nem
    // támogatja az SSE-t, fallback polling marad.
    initChatSSE();
});

// Oldalról való távozáskor jelöljük olvasottnak
window.addEventListener('beforeunload', markAsRead);

// SSE inicializálása a chat üzenetekhez
function initChatSSE() {
    if (typeof EventSource === 'undefined') {
        // Ha az SSE nem támogatott, marad a polling
        setInterval(loadMessages, 2000);
        return;
    }
    
    // ✅ CLEANUP: Amennyiben már van futó SSE kapcsolat, lezárjuk
    if (chatEventSource) {
        chatEventSource.close();
        chatEventSource = null;
    }
    
    chatEventSource = new EventSource('chat_sse.php?last_id=' + lastMessageId);
    
    chatEventSource.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            if (Array.isArray(data.messages) && data.messages.length > 0) {
                const chatMessages = document.getElementById('chatMessages');
                const wasAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;
                data.messages.forEach(msg => {
                    const msgId = parseInt(msg.id);
                    if (msgId > lastMessageId) {
                        lastMessageId = msgId;
                        appendMessage(msg);
                    }
                });
                if (wasAtBottom) {
                    scrollToBottom();
                }
                // Új üzeneteket olvasottnak jelöljük
                markAsRead();
            }
        } catch (e) {
            console.error('Chat SSE adat hiba:', e);
        }
    };
    
    chatEventSource.onerror = function() {
        console.error('Chat SSE kapcsolat hiba. Újracsatlakozás néhány másodperc múlva.');
        
        // ✅ CLEANUP: Properly close the failed connection
        if (chatEventSource) {
            chatEventSource.close();
            chatEventSource = null;
        }
        
        // 5 másodperc múlva megpróbáljuk újra létrehozni az SSE kapcsolatot.
        setTimeout(initChatSSE, 5000);
    };
}
