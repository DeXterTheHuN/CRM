<?php
require_once __DIR__ . '/Repository.php';

class ErrorLogRepository extends Repository
{
    /**
     * Log an error to database
     */
    public function logError(array $errorData): bool
    {
        try {
            $sql = "INSERT INTO error_logs 
                    (error_type, severity, message, file, line, trace, url, method, user_id, user_agent, ip_address, context)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([
                $errorData['error_type'] ?? 'UNKNOWN',
                $errorData['severity'] ?? 'ERROR',
                $errorData['message'] ?? '',
                $errorData['file'] ?? null,
                $errorData['line'] ?? null,
                $errorData['trace'] ?? null,
                $errorData['url'] ?? null,
                $errorData['method'] ?? null,
                $errorData['user_id'] ?? null,
                $errorData['user_agent'] ?? null,
                $errorData['ip_address'] ?? null,
                isset($errorData['context']) ? json_encode($errorData['context'], JSON_UNESCAPED_UNICODE) : null
            ]);
        } catch (Exception $e) {
            // Fallback to file logging if database fails
            error_log("Failed to log error to database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all errors with pagination
     */
    public function getAllErrors(int $page = 1, int $perPage = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $whereClauses = ['1=1'];
        $params = [];

        // Filter by error type
        if (!empty($filters['error_type'])) {
            $whereClauses[] = "error_type = ?";
            $params[] = $filters['error_type'];
        }

        // Filter by severity
        if (!empty($filters['severity'])) {
            $whereClauses[] = "severity = ?";
            $params[] = $filters['severity'];
        }

        // Filter by user
        if (isset($filters['user_id'])) {
            $whereClauses[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }

        // Filter by date range
        if (!empty($filters['date_from'])) {
            $whereClauses[] = "created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $whereClauses[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // Search in message
        if (!empty($filters['search'])) {
            $whereClauses[] = "(message LIKE ? OR file LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereSQL = implode(' AND ', $whereClauses);

        $sql = "SELECT * FROM error_logs WHERE {$whereSQL} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Count errors with filters
     */
    public function countErrors(array $filters = []): int
    {
        $whereClauses = ['1=1'];
        $params = [];

        if (!empty($filters['error_type'])) {
            $whereClauses[] = "error_type = ?";
            $params[] = $filters['error_type'];
        }

        if (!empty($filters['severity'])) {
            $whereClauses[] = "severity = ?";
            $params[] = $filters['severity'];
        }

        if (isset($filters['user_id'])) {
            $whereClauses[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $whereClauses[] = "created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $whereClauses[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $whereClauses[] = "(message LIKE ? OR file LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereSQL = implode(' AND ', $whereClauses);

        $sql = "SELECT COUNT(*) as total FROM error_logs WHERE {$whereSQL}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch();
        return (int) $result['total'];
    }

    /**
     * Get error by ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM error_logs WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Delete old errors (older than X days)
     */
    public function deleteOldErrors(int $days = 30): int
    {
        $sql = "DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days]);

        return $stmt->rowCount();
    }

    /**
     * Clear all errors
     */
    public function clearAll(): bool
    {
        return $this->pdo->exec("TRUNCATE TABLE error_logs") !== false;
    }

    /**
     * Get error statistics
     */
    public function getStatistics(string $period = 'today'): array
    {
        $dateCondition = match ($period) {
            'today' => "DATE(created_at) = CURDATE()",
            'week' => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => "1=1"
        };

        // Count by severity
        $sql = "SELECT severity, COUNT(*) as count FROM error_logs 
                WHERE {$dateCondition} GROUP BY severity";
        $stmt = $this->pdo->query($sql);
        $bySeverity = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Count by type
        $sql = "SELECT error_type, COUNT(*) as count FROM error_logs 
                WHERE {$dateCondition} GROUP BY error_type";
        $stmt = $this->pdo->query($sql);
        $byType = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Total count
        $sql = "SELECT COUNT(*) as total FROM error_logs WHERE {$dateCondition}";
        $stmt = $this->pdo->query($sql);
        $total = $stmt->fetchColumn();

        return [
            'total' => (int) $total,
            'by_severity' => $bySeverity,
            'by_type' => $byType
        ];
    }
}
