<?php

namespace Sync\Database;

use PDO;
use PDOException;
use RuntimeException;

/** Thin PDO wrapper. Every value reaching SQL goes through a bound parameter. */
class Connection
{
    /** @var PDO */
    private $pdo;
    /** @var string */
    private $lastInsertId = '0';
    /** @var int */
    private $transDepth = 0;
    /** @var bool */
    private $transFailed = false;

    public function __construct(array $cfg)
    {
        // Blank credentials almost always mean .env was not read, so name that cause
        // instead of letting MySQL report a puzzling "Access denied for user ''".
        if (($cfg['username'] ?? '') === '' || ($cfg['database'] ?? '') === '') {
            throw new RuntimeException(
                'No database credentials. The .env file was not read: check that it exists in the'
                . ' application root and is readable by the user PHP runs as.'
            );
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $cfg['hostname'] ?? 'localhost',
            $cfg['port'] ?? '3306',
            $cfg['database'] ?? ''
        );
        try {
            $this->pdo = new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            // Keep MySQL's clock aligned with the app's timezone so NOW() and PHP agree.
            $this->pdo->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /** Runs a statement and returns a Result for SELECTs. */
    public function query(string $sql, array $params = []): Result
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $isSelect = stripos(ltrim($sql), 'SELECT') === 0 || stripos(ltrim($sql), 'SHOW') === 0;
        if ($isSelect) {
            return new Result($stmt->fetchAll());
        }
        if (stripos(ltrim($sql), 'INSERT') === 0) {
            $this->lastInsertId = (string) $this->pdo->lastInsertId();
        }
        return new Result([]);
    }

    public function insertID(): int
    {
        return (int) $this->lastInsertId;
    }

    /** Quotes a literal. Used only for values that cannot be bound, such as inside JSON_CONTAINS. */
    public function escape($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return $this->pdo->quote((string) $value);
    }

    public function transStart(): void
    {
        if ($this->transDepth === 0) {
            $this->transFailed = false;
            $this->pdo->beginTransaction();
        }
        $this->transDepth++;
    }

    public function transComplete(): void
    {
        $this->transDepth--;
        if ($this->transDepth === 0 && $this->pdo->inTransaction()) {
            if ($this->transFailed) {
                $this->pdo->rollBack();
            } else {
                $this->pdo->commit();
            }
        }
    }

    public function transRollback(): void
    {
        $this->transFailed = true;
    }

    public function listTables(): array
    {
        $out = [];
        foreach ($this->query('SHOW TABLES')->getResultArray() as $row) {
            $out[] = (string) reset($row);
        }
        return $out;
    }
}
