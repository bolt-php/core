<?php

namespace framework\db\commands;

use framework\db\traits\HasJoin;
use \framework\db\traits\HasTable;
use \framework\db\drivers\BaseDriver;
use framework\db\traits\HasWhere;

class SelectCommand extends BaseCommand
{
    protected array $cols;
    protected array $params = [];
    public $transform = null;
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;

    use HasTable;
    use HasWhere;
    use HasJoin;

    public function __construct(BaseDriver $driver, string|array $cols)
    {
        if (\is_string($cols)) {
            $cols = explode(',', $cols);
        }

        $this->cols = $cols;
        parent::__construct($driver);
    }

    public function sql(): string
    {
        return $this->conn->compile('select', [
            'table' => $this->table,
            'columns' => $this->cols,
            'condition' => $this->where,
            'where' => $this->where,
            'joins' => $this->joins,
            'orders' => $this->orders,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ]);
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];

        return $this;
    }

    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit = $limit;
        $this->offset = $offset;

        return $this;
    }


    public function offset(?int $offset = null)
    {
        $this->offset = $offset;
        return $this;
    }
    protected function transform($data)
    {
        if (is_callable($this->transform)) {
            return array_map($this->transform, $data);
        }

        return $data;
    }

    protected function transformOne($data)
    {
        if ($data === false || $data === null) {
            return null;
        }

        if (is_callable($this->transform)) {
            return ($this->transform)($data);
        }

        return $data;
    }

    public function all()
    {
        $sql = $this->sql();

        return $this->transform($this->conn->execute($sql, $this->params)->fetchAll());
    }

    public function first()
    {
        $sql = $this->sql();
        return $this->transformOne($this->conn->execute($sql, $this->params)->fetch());
    }

    public function count()
    {
        $sql = $this->sql();
        return $this->conn->execute($sql, $this->params)->rowCount();
    }
}