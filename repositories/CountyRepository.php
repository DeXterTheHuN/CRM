<?php
require_once __DIR__ . '/Repository.php';

/**
 * Repository class for accessing county related data.
 */
class CountyRepository extends Repository
{
    /**
     * Fetch all counties along with the number of open clients in each county.
     *
     * A county is considered to have an "open" client if the client is
     * approved and not yet closed (closed_at is NULL or zero-date).
     * The result is ordered alphabetically by county name.
     *
     * @return array[] An array of associative arrays with county data and client_count key
     */
    public function getCountiesWithCounts(): array
    {
        $sql = "
            SELECT c.*, COALESCE(sub.client_count, 0) AS client_count
            FROM counties c
            LEFT JOIN (
                SELECT county_id, COUNT(*) AS client_count
                FROM clients
                WHERE approval_status = 'approved'
                AND (closed_at IS NULL OR closed_at = '0000-00-00 00:00:00')
                GROUP BY county_id
            ) sub ON c.id = sub.county_id
            ORDER BY c.name ASC
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Retrieve a county by its identifier.
     *
     * @param int $id The county ID
     * @return array|null The county row or null if not found
     */
    public function getCountyById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM counties WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }

    /**
     * Retrieve all counties.
     *
     * @return array[]
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM counties ORDER BY name");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}