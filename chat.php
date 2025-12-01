<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #e3f2fd 0%, #e8eaf6 100%);
            min-height: 100vh;
        }
        .header {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .chat-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            height: calc(100vh - 200px);
            display: flex;
            flex-direction: column;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }
        .message {
            margin-bottom: 15px;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message.own {
            text-align: right;
        }
        .message-bubble {
            display: inline-block;
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        .message.own .message-bubble {
            background: #0d6efd;
            color: white;
            border-bottom-right-radius: 4px;
        }
        .message.other .message-bubble {
            background: white;
            color: #333;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .message-sender {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 4px;
            color: #666;
        }
        .message.own .message-sender {
            color: #0d6efd;
        }
        .message-time {
            font-size: 0.7rem;
            color: #999;
            margin-top: 4px;
        }
        .message.own .message-time {
            color: rgba(255,255,255,0.7);
        }
        .chat-input-area {
            padding: 20px;
            background: white;
            border-top: 2px solid #e9ecef;
        }
        .chat-input {
            border: 2px solid #e9ecef;
            border-radius: 24px;
            padding: 12px 20px;
            resize: none;
        }
        .chat-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }
        .send-btn {
            border-radius: 50%;
            width: 48px;
            height: 48px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="mb-0">
                    <i class="bi bi-chat-dots-fill text-primary"></i> Chat
                </h3>
                <div class="d-flex align-items-center gap-2 gap-md-3 flex-wrap justify-content-end">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Vissza
                    </a>
                    <a href="profile.php" class="text-decoration-none text-dark">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <?php if (isAdmin()): ?>
                            <span class="badge bg-primary ms-2">Admin</span>
                        <?php endif; ?>
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i> <span class="d-none d-md-inline">Kijelentkezés</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="chat-container">
            <div class="chat-messages" id="chatMessages">
                <div class="text-center text-muted py-4">
                    <i class="bi bi-chat-dots fs-1"></i>
                    <p>Üzenetek betöltése...</p>
                </div>
            </div>
            <div class="chat-input-area">
                <div class="d-flex gap-2">
                    <textarea 
                        class="form-control chat-input" 
                        id="messageInput" 
                        rows="1" 
                        placeholder="Írj egy üzenetet..."
                        onkeypress="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }"
                    ></textarea>
                    <button class="btn btn-primary send-btn" onclick="sendMessage()">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const userId = <?php echo $user_id; ?>;
        const userName = '<?php echo escape($user_name); ?>';
        let lastMessageId = 0;
        let isLoadingMessages = false;

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

                        // Üzenetek olvasottnak jelölése
                        markAsRead();
                    }
                    isLoadingMessages = false;
                })
                .catch(error => {
                    console.error('Hiba az üzenetek betöltésekor:', error);
                    isLoadingMessages = false;
                });
        }

        // Üzenet hozzáadása a chat-hez
        function appendMessage(msg) {
            const chatMessages = document.getElementById('chatMessages');
            
            // Első üzenet esetén töröljük a "betöltés" szöveget
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
                console.error('Hiba:', error);
                alert('Hiba az üzenet küldésekor');
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
                fetch('chat_api.php?action=mark_read&last_id=' + lastMessageId);
            }
        }

        // HTML escape
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Auto-resize textarea
        document.getElementById('messageInput').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Inicializálás
        loadMessages();
        setInterval(loadMessages, 2000); // Frissítés 2 másodpercenként

        // Oldalról való távozáskor jelöljük olvasottnak
        window.addEventListener('beforeunload', markAsRead);
    </script>
</body>
</html>
