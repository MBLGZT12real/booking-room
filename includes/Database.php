<?php
// ============================================================
// Database PDO Wrapper Class
// ============================================================

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        require_once dirname(__DIR__) . '/config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:monospace;background:#fee;color:#900;padding:20px;border:1px solid #900;margin:20px">
                <strong>Database Connection Error:</strong><br>' . htmlspecialchars($e->getMessage()) .
                '<br><br>Periksa konfigurasi di <code>config/database.php</code>
                </div>');
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getPDO(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute query and return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch single row as associative array
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows as array of associative arrays
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch single column value
     */
    public function fetchColumn(string $sql, array $params = [])
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    /**
     * Insert row and return last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update rows matching conditions
     */
    public function update(string $table, array $data, array $conditions): int
    {
        $set = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys($data)));
        $where = implode(' AND ', array_map(fn($col) => "`$col` = ?", array_keys($conditions)));
        $sql = "UPDATE `$table` SET $set WHERE $where";
        $stmt = $this->query($sql, array_merge(array_values($data), array_values($conditions)));
        return $stmt->rowCount();
    }

    /**
     * Delete rows matching conditions
     */
    public function delete(string $table, array $conditions): int
    {
        $where = implode(' AND ', array_map(fn($col) => "`$col` = ?", array_keys($conditions)));
        $sql = "DELETE FROM `$table` WHERE $where";
        $stmt = $this->query($sql, array_values($conditions));
        return $stmt->rowCount();
    }

    /**
     * Count rows
     */
    public function count(string $table, array $conditions = [], string $extra = ''): int
    {
        $where = '';
        $params = [];
        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', array_map(fn($col) => "`$col` = ?", array_keys($conditions)));
            $params = array_values($conditions);
        }
        return (int) $this->fetchColumn("SELECT COUNT(*) FROM `$table` $where $extra", $params);
    }

    /**
     * Check if record exists
     */
    public function exists(string $table, array $conditions): bool
    {
        return $this->count($table, $conditions) > 0;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }
}

// Global shorthand
function db(): Database
{
    return Database::getInstance();
}
