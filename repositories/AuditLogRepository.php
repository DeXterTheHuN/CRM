<?php
require_once __DIR__ . '/Repository.php';

/**
 * Repository class for retrieving and exporting audit log entries.
 *
 * The audit log holds records of actions performed within the CRM. This
 * repository centralises the filtering, counting and exporting logic so
 * that controllers no longer need to build SQL strings by hand. Filters
 * are passed as an associative array with optional keys: 'user',
 * 'action', 'table', 'date_from', 'date_to' and 'search'.
 */
class AuditLogRepository extends Repository
{
    /**
     * Retrieve a paginated list of audit logs based on the supplied filters.
     *
     * @param array $filters Filter criteria (user, action, table, date_from, date_to, search)
     * @param int   $limit   Number of records per page
     * @param int   $offset  Offset for pagination
     * @return array[]
     */
    public function getAuditLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        list($whereSql, $params) = $this->buildWhere($filters);
        // Build SQL with limit and offset directly as integers to avoid prepared statement limitations
        $sql = "SELECT * FROM audit_logs WHERE {$whereSql} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total audit log records for the given filters.
     *
     * @param array $filters
     * @return int
     */
    public function countAuditLogs(array $filters = []): int
    {
        list($whereSql, $params) = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM audit_logs WHERE {$whereSql}");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['total'] : 0;
    }

    /**
     * Export all audit logs matching the given filters.
     *
     * @param array $filters
     * @return array[]
     */
    public function exportAuditLogs(array $filters = []): array
    {
        list($whereSql, $params) = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("SELECT * FROM audit_logs WHERE {$whereSql} ORDER BY created_at DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a distinct list of users who have entries in the audit log.
     *
     * @return array[] Each element has 'user_id' and 'user_name'
     */
    public function getDistinctUsers(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT user_id, user_name FROM audit_logs WHERE user_id IS NOT NULL ORDER BY user_name");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Get a distinct list of actions recorded in the audit log.
     *
     * @return array[] A list of action strings
     */
    public function getDistinctActions(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    /**
     * Get a distinct list of table names recorded in the audit log.
     *
     * @return array[] A list of table names
     */
    public function getDistinctTables(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    /**
     * Build a dynamic WHERE clause and parameter list based on filters.
     *
     * @param array $filters
     * @return array [string $whereSql, array $params]
     */
    private function buildWhere(array $filters): array
    {
        $conditions = ["1=1"];
        $params = [];
        if (!empty($filters['user'])) {
            $conditions[] = "user_id = ?";
            $params[] = $filters['user'];
        }
        if (!empty($filters['action'])) {
            $conditions[] = "action = ?";
            $params[] = $filters['action'];
        }
        if (!empty($filters['table'])) {
            $conditions[] = "table_name = ?";
            $params[] = $filters['table'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = "created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['search'])) {
            $conditions[] = "(user_name LIKE ? OR old_values LIKE ? OR new_values LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        $whereSql = implode(" AND ", $conditions);
        return [$whereSql, $params];
    }
}