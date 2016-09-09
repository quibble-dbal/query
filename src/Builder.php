<?php

namespace Quibble\Query;

use PDO;

class Builder
{
    private $adapter;
    private $tables = [];
    private $fields = [];
    private $where = ['*'];
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

    public function where($sql, ...$bindables)
    {
        return new static(
            $this->adapter,
            $this->tables,
            [
                'where' => array_merge($this->where, [$sql]),
                'bindables' => array_merge($this->bindables, $bindables),
            ]
        );
    }

    public function orWhere($sql, ...$bindables)
    {
        return $this->where("OR $sql", ...$bindables);
    }

    public function join($table, $style = '', ...$bindables)
    {
        return new static(
            $this->adapter,
            array_merge($this->tables, [sprintf('% JOIN %s', $style, $table)]),
            [
                'where' => $this->where,
                'bindables' => array_merge($this->bindables, $bindables),
            ]
        );
    }

    public function getBindings()
    {
        return $this->bindables;
    }

    public function getSql()
    {
        return $this->__toString();
    }

    public function getStatement($driver_params = null)
    {
        return $this->adapter->prepare($this->__toString(), $driver_params);
    }

    public function __toString()
    {
        return sprintf(
            'SELECT %s FROM %s WHERE %s %s %s',
            implode(', ', $this->fields),
            implode(' ', $this->tables),
            implode(' ', $this->where),
            implode(', ', $this->order),
            ''
        );
    }
}

