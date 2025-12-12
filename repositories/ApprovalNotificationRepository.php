<?php
require_once __DIR__ . '/Repository.php';

/**
 * Repository for approval notifications.
 */
class ApprovalNotificationRepository extends Repository
{
    /**
     * Get all unread approval notifications for a user.
     *
     * @param int $userId
     * @return array[]
     */
    public function getUnreadByUser(int $userId): array
    {
        $sql = "
            SELECT id, client_id, client_name, approval_status, rejection_reason, created_at
            FROM approval_notifications
            WHERE user_id = ? AND read_at IS NULL
            ORDER BY created_at DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark a single notification as read.
     *
     * @param int $notificationId
     * @param int $userId
     * @return void
     */
    public function markRead(int $notificationId, int $userId): void
    {
        $sql = "UPDATE approval_notifications SET read_at = NOW() WHERE id = ? AND user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$notificationId, $userId]);
    }

    /**
     * Mark all notifications for a user as read.
     *
     * @param int $userId
     * @return void
     */
    public function markAllRead(int $userId): void
    {
        $sql = "UPDATE approval_notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
    }

    /**
     * Create a new approval notification.
     *
     * @param int $clientId
     * @param int $userId
     * @param string $clientName
     * @param string $approvalStatus 'approved' or 'rejected'
     * @param string|null $rejectionReason
     * @return void
     */
    public function createNotification(int $clientId, int $userId, string $clientName, string $approvalStatus, ?string $rejectionReason = null): void
    {
        $sql = "INSERT INTO approval_notifications (client_id, user_id, client_name, approval_status, rejection_reason) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clientId, $userId, $clientName, $approvalStatus, $rejectionReason]);
    }
}