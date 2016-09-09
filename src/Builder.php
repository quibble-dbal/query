<?php

namespace Quibble\Query;

use PDO;
use PDOStatement;

class Builder
{
    private $adapter;
    private $tables = [];
    private $fields = ['*'];
    private $wheres = [];
    private $order = [];
    private $bindables = [];

    public function __construct(PDO $adapter, $table, array $init = [])
    {
        $this->adapter = $adapter;
        if (is_string($table)) {
            $table = [$table];
        }
        $this->tables = $table;
        foreach ($init as $key => $value) {
            $this->$key = $value;
        }
    }

    public function where($sql, ...$bindables) : Builder
    {
        return new static(
            $this->adapter,
            $this->tables,
            [
                'wheres' => array_merge($this->wheres, [$sql]),
                'bindables' => array_merge($this->bindables, $bindables),
            ]
        );
    }

    public function andWhere($sql, ...$bindables) : Builder
    {
        if (!$this->wheres) {
            $this->wheres[] = '(1=1)';
        }
        return $this->where("AND ($sql)", ...$bindables);
    }

    public function orWhere($sql, ...$bindables) : Builder
    {
        if ($this->wheres) {
            $sql = "OR ($sql)";
        }
        return $this->where($sql, ...$bindables);
    }

    public function join($table, $style = '', ...$bindables) : Builder
    {
        return new static(
            $this->adapter,
            array_merge($this->tables, [sprintf('% JOIN %s', $style, $table)]),
            [
                'wheres' => $this->wheres,
                'bindables' => array_merge($this->bindables, $bindables),
            ]
        );
    }

    public function getBindings() : array
    {
        return $this->bindables;
    }

    public function getSql() : string
    {
        return $this->__toString();
    }

    public function getStatement($driver_params = null) : PDOStatement
    {
        return $this->adapter->prepare($this->__toString(), $driver_params);
    }

    public function __toString() : string
    {
        return sprintf(
            'SELECT %s FROM %s %s %s %s',
            implode(', ', $this->fields),
            implode(' ', $this->tables),
            $this->wheres ? ' WHERE '.implode(' ', $this->wheres) : '',
            $this->order ? ' ORDER BY '.implode(', ', $this->order) : '',
            ''
        );
    }

    public function __call($fn, array $params = [])
    {
        $stmt = $this->getStatement();
        $stmt->execute($this->getBindings());
        return call_user_func_array([$stmt, $fn], $params);
    }
}

