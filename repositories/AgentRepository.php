<?php
require_once __DIR__ . '/Repository.php';

/**
 * Repository class for accessing agent related data.
 */
class AgentRepository extends Repository
{
    /**
     * Get all agents that are associated with approved users.
     *
     * This joins the agents table with users by the agent's name to ensure
     * only active/approved users are returned. The result is ordered by
     * agent name.
     *
     * @return array[] A list of agents as associative arrays
     */
    public function getActiveAgents(): array
    {
        $sql = "
            SELECT a.*
            FROM agents a
            INNER JOIN users u ON a.name = u.name
            WHERE u.approval_status = 'approved'
            ORDER BY a.name
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Retrieve the agent id of a user by their name.
     *
     * @param string $name
     * @return int|null
     */
    public function getAgentIdByName(string $name): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM agents WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? (int) $result['id'] : null;
    }

    /**
     * Create a new agent record.
     *
     * @param string $name
     * @param string $color
     * @return int The ID of the newly created agent
     */
    public function createAgent(string $name, string $color): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO agents (name, color) VALUES (?, ?)");
        $stmt->execute([$name, $color]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update the colour of an agent.
     *
     * @param int    $agentId
     * @param string $colour Hex colour (e.g. #ff0000)
     * @return void
     */
    public function updateColor(int $agentId, string $colour): void
    {
        $stmt = $this->pdo->prepare("UPDATE agents SET color = ? WHERE id = ?");
        $stmt->execute([$colour, $agentId]);
    }

    /**
     * Get all agents with their user role (joined with users table).
     * Only approved users are included.
     *
     * @return array[]
     */
    public function getAgentsWithRoles(): array
    {
        $sql = "
            SELECT a.*, u.role
            FROM agents a
            INNER JOIN users u ON a.name = u.name
            WHERE u.approval_status = 'approved'
            ORDER BY u.role DESC, a.name ASC
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Retrieve all agents (regardless of associated user).
     *
     * @return array[]
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM agents ORDER BY name");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}