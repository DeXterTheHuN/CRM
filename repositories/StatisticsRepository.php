<?php
require_once __DIR__ . '/Repository.php';

/**
 * Repository class for statistics and reporting operations.
 *
 * Provides methods to retrieve monthly contract statistics and
 * lists of closed clients within a date range. These methods
 * encapsulate the SQL queries used on the statistics page.
 */
class StatisticsRepository extends Repository
{
    /**
     * Get monthly contract statistics for each agent within a date range.
     *
     * The statistics count how many distinct clients signed a contract in
     * the given period. Only approved users are included. Results are
     * ordered by the number of contracts in descending order, then by
     * agent name.
     *
     * @param string $startDate Date in Y-m-d format representing the first day of the month
     * @param string $endDate   Date in Y-m-d format representing the last day of the month
     * @return array[] Each element contains agent_name, user_role and contract_count
     */
    public function getContractStats(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                a.name AS agent_name,
                u.role AS user_role,
                COUNT(DISTINCT c.id) AS contract_count
            FROM agents a
            LEFT JOIN users u ON a.name = u.name
            LEFT JOIN clients c ON c.agent_id = a.id
                AND c.contract_signed = 1
                AND c.approved = 1
                AND DATE(c.contract_signed_at) BETWEEN ? AND ?
            WHERE u.approved = 1
            GROUP BY a.id, a.name, u.role
            ORDER BY contract_count DESC, a.name ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve closed clients within a date range.
     *
     * A closed client is one where closed_at is not null. Only approved
     * clients are considered. The results include county, settlement and
     * agent information.
     *
     * @param string $startDate Start date in Y-m-d format
     * @param string $endDate   End date in Y-m-d format
     * @return array[]
     */
    public function getClosedClients(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                c.id,
                c.name,
                c.closed_at,
                co.name AS county_name,
                s.name AS settlement_name,
                a.name AS agent_name,
                a.color AS agent_color
            FROM clients c
            LEFT JOIN counties co ON c.county_id = co.id
            LEFT JOIN settlements s ON c.settlement_id = s.id
            LEFT JOIN agents a ON c.agent_id = a.id
            WHERE c.closed_at IS NOT NULL
              AND c.approved = 1
              AND DATE(c.closed_at) BETWEEN ? AND ?
            ORDER BY c.closed_at DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}