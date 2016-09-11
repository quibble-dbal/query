<?php

namespace Quibble\Query;

use PDO;
use PDOStatement;

abstract class Builder
{
    protected $adapter;
    protected $tables = [];
    protected $fields = ['*'];
    protected $wheres = [];
    protected $group = null;
    protected $orders = [];
    protected $bindables = [];
    private $statement;

    public function __construct(PDO $adapter, $table)
    {
        $this->adapter = $adapter;
        if (is_string($table)) {
            $table = [$table];
        }
        $this->tables = $table;
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
        if (!isset($this->statement)) {
            $this->statement = $this->adapter->prepare(
                $this->__toString(),
                $driver_params
            );
        }
        return $this->statement;
    }

    public function getExecutedStatement($driver_params = null) : PDOStatement
    {
        $stmt = $this->getStatement();
        $stmt->execute($this->getBindings());
        return $stmt;
    }

    abstract public function __toString() : string;

    public function reset()
    {
        $this->statement = null;
    }

    public function __call($fn, array $params = [])
    {
        $stmt = $this->getStatement();
        $stmt->execute($this->getBindings());
        return $stmt->$fn(...$params);
    }
}

