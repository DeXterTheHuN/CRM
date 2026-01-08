<?php
require_once __DIR__ . '/Repository.php';

/**
 * Repository for notification and client view related operations.
 */
class NotificationRepository extends Repository
{
    /**
     * Get the agent_id for a user by looking up their name in the agents table.
     * Returns null if no matching agent is found.
     *
     * @param int $userId
     * @return int|null
     */
    protected function getAgentIdForUser(int $userId): ?int
    {
        $sql = "
            SELECT a.id FROM agents a
            INNER JOIN users u ON a.name = u.name
            WHERE u.id = :user_id
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (int) $result : null;
    }

    /**
     * Get count of pending client approvals.
     *
     * @return int
     */
    public function getPendingApprovalsCount(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS pending_count FROM clients WHERE approval_status = 'pending'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['pending_count'] : 0;
    }

    /**
     * Get count of new (unviewed) clients for a user.
     *
     * Only counts clients that are approved and not closed.
     *
     * @param int  $userId
     * @param bool $isAdmin
     * @return int
     */
    public function getNewClientsTotal(int $userId, bool $isAdmin): int
    {
        $sql = "
            SELECT COUNT(DISTINCT c.id) AS new_count
            FROM clients c
            LEFT JOIN client_views cv ON c.id = cv.client_id AND cv.user_id = :user_id
            WHERE c.approval_status = 'approved'
              AND (c.closed_at IS NULL OR c.closed_at = '0000-00-00 00:00:00')
              AND cv.viewed_at IS NULL
        ";
        if (!$isAdmin) {
            // Ügynököknél csak a saját (vagy még nem kiosztott) ügyfelek számítanak újként.
            $sql .= " AND (c.agent_id = :agent_id OR c.agent_id IS NULL)";
        }

        $stmt = $this->pdo->prepare($sql);

        // Properly look up agent_id from agents table by user name
        $params = ['user_id' => $userId];
        if (!$isAdmin) {
            $agentId = $this->getAgentIdForUser($userId);
            $params['agent_id'] = $agentId ?? 0;
        }

        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['new_count'] : 0;
    }

    /**
     * Get count of new (unviewed) clients by county.
     *
     * @param int  $userId
     * @param bool $isAdmin
     * @return array[] Each item contains county_id, county_name and new_count
     */
    public function getNewClientsByCounty(int $userId, bool $isAdmin): array
    {
        $sql = "
            SELECT c.county_id, co.name AS county_name, COUNT(DISTINCT c.id) AS new_count
            FROM clients c
            JOIN counties co ON c.county_id = co.id
            LEFT JOIN client_views cv ON c.id = cv.client_id AND cv.user_id = :user_id
            WHERE c.approval_status = 'approved'
              AND (c.closed_at IS NULL OR c.closed_at = '0000-00-00 00:00:00')
              AND cv.viewed_at IS NULL
        ";
        if (!$isAdmin) {
            // Ügynököknél itt is csak a saját/„gazdátlan” ügyfelek számítanak.
            $sql .= " AND (c.agent_id = :agent_id OR c.agent_id IS NULL)";
        }
        $sql .= " GROUP BY c.county_id, co.name HAVING new_count > 0";

        $stmt = $this->pdo->prepare($sql);

        // Properly look up agent_id from agents table by user name
        $params = ['user_id' => $userId];
        if (!$isAdmin) {
            $agentId = $this->getAgentIdForUser($userId);
            $params['agent_id'] = $agentId ?? 0;
        }

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark a client as viewed by a user (or update timestamp if already viewed).
     *
     * @param int $clientId
     * @param int $userId
     * @return void
     */
    public function markClientViewed(int $clientId, int $userId): void
    {
        $sql = "
            INSERT INTO client_views (client_id, user_id, viewed_at)
            VALUES (:client_id, :user_id, NOW())
            ON DUPLICATE KEY UPDATE viewed_at = NOW()
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['client_id' => $clientId, 'user_id' => $userId]);
    }

    /**
     * Mark all clients in a county as viewed by a user.
     * Returns the number of clients marked.
     *
     * @param int  $countyId
     * @param int  $userId
     * @param bool $isAdmin
     * @return int
     */
    public function markCountyClientsViewed(int $countyId, int $userId, bool $isAdmin): int
    {
        // Fetch clients in county that are approved and not closed, and visible for user (if not admin)
        $sql = "
            SELECT id
            FROM clients
            WHERE county_id = :county_id
              AND approval_status = 'approved'
              AND (closed_at IS NULL OR closed_at = '0000-00-00 00:00:00')
        ";
        if (!$isAdmin) {
            $sql .= " AND (agent_id = :agent_id OR agent_id IS NULL)";
        }
        $stmt = $this->pdo->prepare($sql);
        $params = ['county_id' => $countyId];
        if (!$isAdmin) {
            $agentId = $this->getAgentIdForUser($userId);
            $params['agent_id'] = $agentId ?? 0;
        }
        $stmt->execute($params);
        $clients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($clients)) {
            return 0;
        }
        // Build bulk insert values
        $values = [];
        $binds = [];
        foreach ($clients as $clientId) {
            $values[] = '(?, ?, NOW())';
            $binds[] = $clientId;
            $binds[] = $userId;
        }
        $sqlInsert = "INSERT INTO client_views (client_id, user_id, viewed_at) VALUES " . implode(', ', $values) . " ON DUPLICATE KEY UPDATE viewed_at = NOW()";
        $bulkStmt = $this->pdo->prepare($sqlInsert);
        $bulkStmt->execute($binds);
        return count($clients);
    }
}
