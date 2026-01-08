<?php
/**
 * Base repository class.
 *
 * All repository classes should extend this class and have access to the
 * shared PDO instance. The repository pattern helps to centralise all
 * database queries into dedicated classes instead of spreading SQL across
 * your application logic. This makes it easier to maintain and optimise
 * queries and encourages separation of concerns.
 */
abstract class Repository
{
    /**
     * The PDO connection instance.
     *
     * @var PDO
     */
    protected PDO $pdo;

    /**
     * Create a new repository instance.
     *
     * @param PDO $pdo The PDO connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
}