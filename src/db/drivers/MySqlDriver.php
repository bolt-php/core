<?php

namespace framework\db\drivers;

use Exception;
use framework\db\QueryResult;
use PDO;

class MySqlDriver extends BaseDriver
{
    protected $conn;

    protected function getDsn(): string
    {
        $dsn = "mysql:host={$this->config['host']};dbname={$this->config['database']}";

        if (isset($this->config['port'])) {
            $dsn .= ";port={$this->config['port']}";
        }

        return $dsn;
    }

    public function connect(): void
    {
        // The connection is lazy loaded instead
    }

    protected function conn()
    {
        if (is_null($this->conn)) {
            $this->conn = new \PDO($this->getDsn(), $this->config['username'], $this->config['password']);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        return $this->conn;
    }

    public function disconnect(): void
    {
        $this->conn = null;
    }

    public function compile(string $type, array $components): string
    {
        switch ($type) {
            case 'select':
                return $this->compileSelect($components);
            case 'update':
                return $this->compileUpdate($components);
            case 'insert':
                return $this->compileInsert($components);
            case 'delete':
                return $this->compileDelete($components);
            case 'createTable':
                return $this->compileCreateTable($components);
            case 'tableExists':
                return $this->compileTableExists($components);
            case 'dropTable':
                return $this->compileDropTable($components);
            case 'transaction':
                return $this->compileTransaction($components);
            default:
                throw new Exception("Unsupported query type: {$type}");
        }
    }

    protected function compileSelect(array $components): string
    {
        $cols = implode(', ', $components['columns'] ?? ['*']);
        $query = "SELECT {$cols} FROM {$components['table']}";

        foreach ($components['joins'] as $join) {
            $query .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        $query = $this->compileWhere($query, $components['where']);

        if (!empty($components['orders'])) {
            $query .= $this->compileOrderBy($components['orders']);
        }

        if (isset($components['limit'])) {
            $query .= $this->compileLimit($components['limit'], $components['offset'] ?? null);
        }

        return $query;
    }

    protected function compileLimit(int $limit, ?int $offset = null): string
    {
        $sql = " LIMIT {$limit}";

        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        return $sql;
    }

    protected function compileDelete(array $components): string
    {
        $query = "DELETE FROM {$components['table']}";

        $query = $this->compileWhere($query, $components['where']);

        return $query;
    }

    protected function compileInsert(array $components)
    {
        $cols = implode(', ', array_keys($components['columns']));
        $placeholders = implode(', ', array_map(fn($key) => ":$key", array_keys($components['columns'])));

        return "INSERT INTO {$components['table']} ({$cols}) VALUES ({$placeholders})";
    }

    protected function compileUpdate(array $components)
    {
        $query = 'UPDATE ' . $components['table'] . ' SET ';

        foreach ($components['columns'] as $key => $_) {
            $query .= "$key = :$key,";
        }

        $query = \rtrim($query, ',');

        $query = $this->compileWhere($query, $components['where']);

        return $query;
    }

    protected function compileWhere(string $query, array $where, bool $root = true)
    {
        if ($root) {
            $query .= ' WHERE 1 = 1';
        }

        foreach ($where as $i => $cond) {
            $operator = ($root || $i > 0) ? " {$cond['operator']}" : "";

            if (isset($cond['type']) && $cond['type'] === 'group') {
                $query .= "{$operator} (";
                $query = $this->compileWhere($query, $cond['conditions'], false);
                $query .= ")";
            } else {
                $query .= "{$operator} {$cond['condition']}";
            }
        }

        return $query;
    }

    protected function compileOrderBy(array $orders): string
    {
        $orderSql = ' ORDER BY ';
        $parts = [];
        foreach ($orders as $order) {
            $parts[] = "{$order['column']} {$order['direction']}";
        }
        return $orderSql . implode(', ', $parts);
    }

    protected function compileCreateTable(array $components): string
    {
        $table = $components['table'];
        $columnSqls = [];

        foreach ($components['columns'] as $col) {
            $columnDef = "`{$col['name']}` " . $this->mapType($col['type'], $col['attributes']);

            if (isset($col['nullable']) && $col['nullable'] === false) {
                $columnDef .= " NOT NULL";
            } else {
                $columnDef .= " NULL";
            }

            if (isset($col['default']) && $col['default'] !== null) {
                $columnDef .= " DEFAULT " . $this->quoteValue($col['default']);
            }

            if (isset($col['attributes']['autoIncrement']) && $col['attributes']['autoIncrement'] === true) {
                $columnDef .= " AUTO_INCREMENT";
            }

            if (isset($col['attributes']['primary']) && $col['attributes']['primary'] === true) {
                $columnDef .= " PRIMARY KEY";
            }

            if (isset($col['attributes']['unique']) && $col['attributes']['unique'] === true) {
                $columnDef .= " UNIQUE";
            }

            $columnSqls[] = $columnDef;
        }

        $columns = implode(", ", $columnSqls);
        return "CREATE TABLE `{$table}` ({$columns})";
    }

    protected function compileTableExists(array $components): string
    {
        return "SHOW TABLES LIKE '{$components['table']}'";
    }

    protected function compileDropTable(array $components): string
    {
        return "DROP TABLE `{$components['table']}`";
    }

    protected function compileTransaction(array $components): string
    {
        switch ($components['action']) {
            case 'begin':
                return "START TRANSACTION";
            case 'commit':
                return "COMMIT";
            case 'rollback':
                return "ROLLBACK";
            default:
                throw new Exception("Unsupported transaction action: {$components['action']}");
        }
    }

    protected function mapType(string $type, array $attributes): string
    {
        switch ($type) {
            case 'number':
                return "DECIMAL(10, 0)";
            case 'integer':
                return "INT";
            case 'decimal':
                $precision = $attributes['precision'] ?? 8;
                $scale = $attributes['scale'] ?? 2;
                return "DECIMAL({$precision}, {$scale})";
            case 'string':
                $length = $attributes['length'] ?? 255;
                return "VARCHAR({$length})";
            case 'char':
                $length = $attributes['length'] ?? 255;
                return "CHAR({$length})";
            case 'date':
                return "DATE";
            case 'dateTime':
                return "DATETIME";
            case 'time':
                return "TIME";
            case 'timestamp':
                return "TIMESTAMP";
            case 'boolean':
                return "BOOLEAN";
            default:
                return "VARCHAR(255)";
        }
    }

    protected function quoteValue($value): string
    {
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value === null) {
            return 'NULL';
        }
        return (string) $value;
    }

    public function execute(string $sql, array $params = []): QueryResult
    {
        $stmt = $this->conn()->prepare($sql);
        $stmt->execute($params);
        return new MySqlResult($stmt);
    }

    public function lastInsertId(): int
    {
        return $this->conn->lastInsertId();
    }
}