<?php
require_once __DIR__ . '/Repository.php';

/**
 * Repository class for user related operations.
 */
class UserRepository extends Repository
{
    /**
     * Get all users ordered by approval status, role and name.
     *
     * @return array[]
     */
    public function getAllUsers(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY approval_status ASC, role DESC, name ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Get the number of users that are not yet approved.
     *
     * @return int
     */
    public function getPendingApprovalCount(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE approval_status = 'pending'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Approve a user (set approval_status = 'approved').
     *
     * @param int $userId
     * @return void
     */
    public function approveUser(int $userId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET approval_status = 'approved',
                approved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }

    /**
     * Unapprove a user (set approval_status = 'pending').
     *
     * @param int $userId
     * @return void
     */
    public function unapproveUser(int $userId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET approval_status = 'pending',
                approved_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }

    /**
     * Delete a user by ID, only if not admin.
     *
     * @param int $userId
     * @return void
     */
    public function deleteUser(int $userId): void
    {
        // Delete only non-admin users
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$userId]);
    }

    /**
     * Update the role of a user.
     *
     * @param int    $userId
     * @param string $newRole
     * @return void
     */
    public function updateUserRole(int $userId, string $newRole): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $userId]);
    }

    /**
     * Get all approved users.
     *
     * @return array[]
     */
    public function getApprovedUsers(): array
    {
        $stmt = $this->pdo->query("SELECT id, name FROM users WHERE approval_status = 'approved'");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Retrieve a user record by their username.
     *
     * This method looks up a user by the unique username and returns
     * the user row or null if not found. It mirrors the logic used in
     * the login and registration code without exposing direct SQL in
     * controllers.
     *
     * @param string $username
     * @return array|null
     */
    public function getUserByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Check whether a user with the given username already exists.
     *
     * @param string $username
     * @return bool True if a user with this username exists, false otherwise
     */
    public function userExists(string $username): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Create a new user record.
     *
     * @param string $username
     * @param string $passwordHash
     * @param string $name
     * @param string|null $email
     * @param string $role
     * @param string $approvalStatus Default 'pending'
     * @return int The newly created user's ID
     */
    public function createUser(string $username, string $passwordHash, string $name, ?string $email, string $role = 'agent', string $approvalStatus = 'pending'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, password, name, email, role, approval_status) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$username, $passwordHash, $name, $email, $role, $approvalStatus]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update the last_login timestamp for a user.
     *
     * @param int $id
     * @return void
     */
    public function updateLastLogin(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Retrieve a single user by their ID.
     *
     * @param int $id
     * @return array|null
     */
    public function getUserById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Update a user's details.
     *
     * If a password hash is provided, it will also be updated. Otherwise the
     * existing password remains unchanged.
     *
     * @param int         $id
     * @param string      $name
     * @param string      $email
     * @param string|null $passwordHash
     * @return void
     */
    public function updateUser(int $id, string $name, string $email, ?string $passwordHash = null): void
    {
        if ($passwordHash !== null) {
            $sql = "UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$name, $email, $passwordHash, $id]);
        } else {
            $sql = "UPDATE users SET name = ?, email = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$name, $email, $id]);
        }
    }

    /**
     * Create default agent entries for approved users who do not yet have one.
     *
     * This replicates the previous logic of automatically creating an agent record for every
     * approved user with a default colour.
     *
     * @param AgentRepository $agentRepo
     * @param string $defaultColor
     * @return void
     */
    public function ensureDefaultAgents(AgentRepository $agentRepo, string $defaultColor = '#808080'): void
    {
        $users = $this->getApprovedUsers();
        foreach ($users as $user) {
            $agentId = $agentRepo->getAgentIdByName($user['name']);
            if ($agentId === null) {
                $agentRepo->createAgent($user['name'], $defaultColor);
            }
        }
    }
}