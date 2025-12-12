<?php
require_once __DIR__ . '/Repository.php';

/**
 * Repository class for patchnotes operations.
 *
 * Handles creation, deletion, marking as read and fetching patchnotes
 * along with their read status for a given user. This keeps
 * patchnotes-related SQL separate from the controllers.
 */
class PatchnotesRepository extends Repository
{
    /**
     * Insert a new patchnote and return its ID.
     *
     * @param string $version   Semantic version (e.g. '1.2.0')
     * @param string $title     Title of the patch note
     * @param string $content   Description of changes
     * @param string $createdBy Name of the user who created the note
     * @param int    $isMajor   1 if major update (popup), 0 otherwise
     * @return int
     */
    public function addPatchnote(string $version, string $title, string $content, string $createdBy, int $isMajor = 0): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO patchnotes (version, title, content, created_by, is_major) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$version, $title, $content, $createdBy, $isMajor]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Delete a patchnote by its ID.
     *
     * @param int $id
     * @return void
     */
    public function deletePatchnote(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM patchnotes WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Mark one or more patchnotes as read for a user.
     *
     * Uses INSERT IGNORE to avoid duplicates.
     *
     * @param int   $userId
     * @param array $patchnoteIds
     * @return void
     */
    public function markRead(int $userId, array $patchnoteIds): void
    {
        if (empty($patchnoteIds)) {
            return;
        }
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO patchnotes_read_status (user_id, patchnote_id) VALUES (?, ?)"
        );
        foreach ($patchnoteIds as $id) {
            $stmt->execute([$userId, $id]);
        }
    }

    /**
     * Get the count of unread patchnotes for a user.
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS unread_count
             FROM patchnotes p
             WHERE NOT EXISTS (
                 SELECT 1 FROM patchnotes_read_status
                 WHERE user_id = ? AND patchnote_id = p.id
             )"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['unread_count'] : 0;
    }

    /**
     * Get the latest unread major patchnote for a user.
     *
     * @param int $userId
     * @return array|null
     */
    public function getLatestUnreadMajor(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*
             FROM patchnotes p
             WHERE p.is_major = 1
               AND NOT EXISTS (
                   SELECT 1 FROM patchnotes_read_status
                   WHERE user_id = ? AND patchnote_id = p.id
               )
             ORDER BY p.created_at DESC
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Retrieve all patchnotes along with read status for a given user.
     *
     * The is_read field will be 1 if the user has read the patchnote,
     * otherwise 0.
     *
     * @param int $userId
     * @return array[]
     */
    public function getAllWithReadStatus(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, 
                    EXISTS(
                        SELECT 1 FROM patchnotes_read_status
                        WHERE user_id = ? AND patchnote_id = p.id
                    ) AS is_read
             FROM patchnotes p
             ORDER BY p.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}