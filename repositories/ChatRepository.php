<?php
require_once __DIR__ . '/Repository.php';

/**
 * Repository for chat related operations.
 */
class ChatRepository extends Repository
{
    /**
     * Get chat messages after a specific ID.
     *
     * @param int $lastId Last seen message ID
     * @param int $limit Number of messages to return
     * @return array[]
     */
    public function getMessages(int $lastId = 0, int $limit = 50): array
    {
        $sql = "
            SELECT id, user_id, user_name, message, created_at
            FROM chat_messages
            WHERE id > ?
            ORDER BY created_at ASC
            LIMIT ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $lastId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert a new chat message.
     *
     * @param int $userId
     * @param string $userName
     * @param string $message
     * @return int The ID of the inserted message
     */
    public function sendMessage(int $userId, string $userName, string $message): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO chat_messages (user_id, user_name, message) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $userName, $message]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update the read status for a user.
     *
     * @param int $userId
     * @param int $lastReadId
     * @return void
     */
    public function markRead(int $userId, int $lastReadId): void
    {
        $sql = "
            INSERT INTO chat_read_status (user_id, last_read_message_id, last_read_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                last_read_message_id = VALUES(last_read_message_id),
                last_read_at = NOW()
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $lastReadId]);
    }

    /**
     * Get unread messages count for a user.
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        // Get last read message id
        $stmt = $this->pdo->prepare("SELECT last_read_message_id FROM chat_read_status WHERE user_id = ?");
        $stmt->execute([$userId]);
        $readStatus = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastReadId = $readStatus ? (int)$readStatus['last_read_message_id'] : 0;
        
        // Count unread messages (not by current user)
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS unread_count FROM chat_messages WHERE id > ? AND user_id != ?");
        $stmt->execute([$lastReadId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['unread_count'] : 0;
    }
}