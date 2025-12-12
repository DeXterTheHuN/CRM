<?php
require_once __DIR__ . '/Repository.php';

/**
 * Repository class for accessing settlement related data.
 */
class SettlementRepository extends Repository
{
    /**
     * Retrieve all settlements for a given county.
     *
     * @param int $countyId The county identifier
     * @return array[] A list of settlements as associative arrays
     */
    public function getSettlementsByCounty(int $countyId): array
    {
        $stmt = $this->pdo->prepare("SELECT id, name FROM settlements WHERE county_id = ? ORDER BY name ASC");
        $stmt->execute([$countyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve all settlements.
     *
     * @return array[]
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM settlements ORDER BY name");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}