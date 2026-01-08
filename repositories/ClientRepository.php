<?php
require_once __DIR__ . '/Repository.php';

/**
 * Repository class for accessing client related data.
 */
class ClientRepository extends Repository
{
    /**
     * Retrieve a single client with its settlement name.
     *
     * @param int $clientId
     * @return array|null An associative array containing client data and settlement_name
     */
    public function getClientByIdWithSettlement(int $clientId): ?array
    {
        $sql = "
            SELECT c.*, s.name AS settlement_name
            FROM clients c
            LEFT JOIN settlements s ON c.settlement_id = s.id
            WHERE c.id = ?
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        return $client !== false ? $client : null;
    }

    /**
     * Get all clients awaiting approval with related county, settlement, agent and creator names.
     *
     * @return array[]
     */
    public function getPendingClients(): array
    {
        $sql = "
            SELECT c.*, 
                   co.name AS county_name, 
                   s.name AS settlement_name,
                   a.name AS agent_name,
                   u.name AS creator_name
            FROM clients c
            LEFT JOIN counties co ON c.county_id = co.id
            LEFT JOIN settlements s ON c.settlement_id = s.id
            LEFT JOIN agents a ON c.agent_id = a.id
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.approval_status = 'pending'
            ORDER BY c.created_at DESC
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Determine if a user is allowed to view a given client.
     *
     * A client can be viewed if it is approved and, for non-admin users,
     * if the client's agent_id matches the user's agent_id (from agents table) or is NULL.
     *
     * @param int  $clientId The client ID to check.
     * @param int  $userId   The currently logged in user's ID.
     * @param bool $isAdmin  Whether the user has admin privileges.
     * @return bool True if the user can view the client, false otherwise.
     */
    public function canUserViewClient(int $clientId, int $userId, bool $isAdmin): bool
    {
        // Admin can view all approved clients
        if ($isAdmin) {
            $sql = "SELECT id FROM clients WHERE id = :client_id AND approval_status = 'approved'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['client_id' => $clientId]);
            return $stmt->fetchColumn() !== false;
        }

        // For non-admin users, we need to get their agent_id from the agents table
        // by looking up their name in the users table first
        $agentSql = "
            SELECT a.id FROM agents a
            INNER JOIN users u ON a.name = u.name
            WHERE u.id = :user_id
            LIMIT 1
        ";
        $agentStmt = $this->pdo->prepare($agentSql);
        $agentStmt->execute(['user_id' => $userId]);
        $agentId = $agentStmt->fetchColumn();

        // Now check if client can be viewed:
        // - Must be approved
        // - agent_id must match user's agent_id OR be NULL (unassigned)
        $sql = "SELECT id FROM clients WHERE id = :client_id AND approval_status = 'approved' AND (agent_id = :agent_id OR agent_id IS NULL)";
        $stmt = $this->pdo->prepare($sql);
        $params = ['client_id' => $clientId, 'agent_id' => $agentId ?: 0];
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Get all clients created by a specific user along with related data.
     *
     * The result is ordered by approval status (pending, approved, rejected)
     * and creation date, matching the previous logic used in my_requests.php.
     *
     * @param int $userId
     * @return array[]
     */
    public function getClientsByCreatorId(int $userId): array
    {
        $sql = "
            SELECT c.*, 
                   co.name AS county_name, 
                   s.name AS settlement_name,
                   a.name AS agent_name,
                   approver.name AS approver_name
            FROM clients c
            LEFT JOIN counties co ON c.county_id = co.id
            LEFT JOIN settlements s ON c.settlement_id = s.id
            LEFT JOIN agents a ON c.agent_id = a.id
            LEFT JOIN users approver ON c.approved_by = approver.id
            WHERE c.created_by = ?
            ORDER BY 
                CASE c.approval_status
                    WHEN 'pending' THEN 1
                    WHEN 'approved' THEN 2
                    WHEN 'rejected' THEN 3
                END,
                c.created_at DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count the number of open, approved clients in a county with optional search and agent filter.
     *
     * This replicates the logic used on the county page to determine the total
     * number of records for pagination. A client is considered "open" if
     * approved = 1 and closed_at is NULL. Search terms match against the
     * client's name, settlement name, phone or email. The agent filter can
     * specify a particular agent ID or 'none' for unassigned clients.
     *
     * @param int    $countyId
     * @param string $search        Search term (partial, will be wrapped with %)
     * @param string $filterAgent   Agent ID or 'none' or '' for no filter
     * @return int
     */
    public function countClientsByCounty(int $countyId, string $search = '', string $filterAgent = ''): int
    {
        $conditions = [
            'c.county_id = :county_id',
            "c.approval_status = 'approved'",
            '(c.closed_at IS NULL OR c.closed_at = "0000-00-00 00:00:00")'
        ];
        $params = ['county_id' => $countyId];
        if ($search !== '') {
            // Use unique parameter names for each LIKE to avoid the "invalid parameter number" error
            $conditions[] = '(
                c.name LIKE :search_name
                OR s.name LIKE :search_settlement
                OR c.phone LIKE :search_phone
                OR c.email LIKE :search_email
            )';
            $like = '%' . $search . '%';
            $params['search_name'] = $like;
            $params['search_settlement'] = $like;
            $params['search_phone'] = $like;
            $params['search_email'] = $like;
        }
        if ($filterAgent !== '') {
            if ($filterAgent === 'none') {
                $conditions[] = 'c.agent_id IS NULL';
            } else {
                $conditions[] = 'c.agent_id = :agent_id';
                $params['agent_id'] = (int) $filterAgent;
            }
        }
        $whereSql = implode(' AND ', $conditions);
        $sql = "SELECT COUNT(*) AS total FROM clients c LEFT JOIN settlements s ON c.settlement_id = s.id WHERE {$whereSql}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['total'] : 0;
    }

    /**
     * Retrieve a paginated list of open, approved clients in a county with optional search, agent filter and sorting.
     *
     * Sorting options mirror those of the county page: 'settlement_asc', 'settlement_desc'
     * or default (by created_at descending). The results include settlement name,
     * agent name and agent colour. Only clients with approved = 1 and closed_at IS NULL are returned.
     *
     * @param int    $countyId
     * @param string $search        Search term (partial)
     * @param string $filterAgent   Agent ID or 'none' or '' for no filter
     * @param string $sortBy        Sorting key
     * @param int    $limit         Number of records to return
     * @param int    $offset        Offset for pagination
     * @return array[]
     */
    public function getClientsByCounty(int $countyId, string $search = '', string $filterAgent = '', string $sortBy = 'created_at', int $limit = CLIENTS_PER_PAGE, int $offset = 0): array
    {
        $conditions = [
            'c.county_id = :county_id',
            "c.approval_status = 'approved'",
            '(c.closed_at IS NULL OR c.closed_at = "0000-00-00 00:00:00")'
        ];
        $params = ['county_id' => $countyId];
        if ($search !== '') {
            // Use unique parameter names for each LIKE to avoid the "invalid parameter number" error
            $conditions[] = '(
                c.name LIKE :search_name
                OR s.name LIKE :search_settlement
                OR c.phone LIKE :search_phone
                OR c.email LIKE :search_email
            )';
            $like = '%' . $search . '%';
            $params['search_name'] = $like;
            $params['search_settlement'] = $like;
            $params['search_phone'] = $like;
            $params['search_email'] = $like;
        }
        if ($filterAgent !== '') {
            if ($filterAgent === 'none') {
                $conditions[] = 'c.agent_id IS NULL';
            } else {
                $conditions[] = 'c.agent_id = :agent_id';
                $params['agent_id'] = (int) $filterAgent;
            }
        }
        // Build order by clause
        $orderBy = 'c.created_at DESC';
        if ($sortBy === 'settlement_asc') {
            $orderBy = 's.name ASC, c.created_at DESC';
        } elseif ($sortBy === 'settlement_desc') {
            $orderBy = 's.name DESC, c.created_at DESC';
        }
        $whereSql = implode(' AND ', $conditions);
        $sql = "
            SELECT
                c.*,
                s.name AS settlement_name,
                a.name AS agent_name,
                a.color AS agent_color
            FROM clients c
            LEFT JOIN settlements s ON c.settlement_id = s.id
            LEFT JOIN agents a ON c.agent_id = a.id
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete multiple clients by their IDs.
     *
     * Returns the number of rows affected. This method can be used for bulk
     * deletions on the county page.
     *
     * @param int[] $clientIds
     * @return int
     */
    public function deleteClients(array $clientIds): int
    {
        if (empty($clientIds)) {
            return 0;
        }
        // Prepare placeholders and values
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $sql = "DELETE FROM clients WHERE id IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($clientIds);
        return $stmt->rowCount();
    }

    /**
     * Retrieve a client by its ID.
     *
     * @param int $clientId
     * @return array|null
     */
    public function getClientById(int $clientId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
        $stmt->execute([$clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Reopen a closed client by resetting closed_at and completion flags.
     *
     * This sets closed_at to NULL (and zero-date), contract_signed and work_completed
     * to 0, effectively reopening the client record. Used in statistics.php for
     * reactivating clients.
     *
     * @param int $clientId
     * @return void
     */
    public function reopenClient(int $clientId): void
    {
        $sql = "UPDATE clients SET closed_at = NULL, contract_signed = 0, work_completed = 0 WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clientId]);
    }

    /**
     * Update a client with a set of columns and values.
     *
     * This method allows flexible updates by accepting an associative array
     * of column => value pairs. It constructs the SET clause dynamically
     * and binds parameters accordingly. Caller must ensure that only
     * trusted columns are passed to avoid SQL injection.
     *
     * @param int   $clientId
     * @param array $fields   Associative array of column => value
     * @return void
     */
    public function updateClient(int $clientId, array $fields): void
    {
        if (empty($fields)) {
            return;
        }
        $setParts = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $setParts[] = "{$column} = ?";
            $params[] = $value;
        }
        $params[] = $clientId;
        $sql = "UPDATE clients SET " . implode(', ', $setParts) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Approve a pending client.
     *
     * Sets approval_status = 'approved', approved_at to NOW,
     * and approved_by to the provided user ID.
     *
     * @param int $clientId
     * @param int $approvedBy User ID who approved the client
     * @return void
     */
    public function approveClient(int $clientId, int $approvedBy): void
    {
        $sql = "UPDATE clients SET approval_status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$approvedBy, $clientId]);
    }

    /**
     * Reject a pending client with a reason.
     *
     * Sets approval_status = 'rejected', rejection_reason,
     * approved_at to NOW, and approved_by to the provided user ID.
     *
     * @param int    $clientId
     * @param int    $rejectedBy     User ID who rejected the client
     * @param string $rejectionReason Reason for rejection
     * @return void
     */
    public function rejectClient(int $clientId, int $rejectedBy, string $rejectionReason): void
    {
        $sql = "UPDATE clients SET approval_status = 'rejected', rejection_reason = ?, approved_at = NOW(), approved_by = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$rejectionReason, $rejectedBy, $clientId]);
    }
}