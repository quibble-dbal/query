<?php

namespace Quibble\Query;

use Quibble\Dabble\SqlException;
use PDO;
use PDOStatement;

abstract class Builder
{
    protected $adapter;
    protected $tables = [];
    protected $bindables = [];
    private $statements = [];

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
        $sql = $this->__toString();
        if (!isset($this->statements[$sql])) {
            $this->statements[$sql] = $this->adapter->prepare(
                $sql,
                $driver_params
            );
            if (!$this->statements[$sql]) {
                unset($this->statements[$sql]);
                throw new SqlException($this->__toString());
            }
        }
        return $this->statements[$sql];
    }

    public function getExecutedStatement($driver_params = null) : PDOStatement
    {
        $stmt = $this->getStatement($driver_params);
        foreach (array_values($this->bindables) as $key => $value) {
            if (is_null($value)) {
                $stmt->bindValue($key + 1, $value, PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $stmt->bindValue($key + 1, $value, PDO::PARAM_BOOL);
            } else {
                $stmt->bindValue($key + 1, $value, PDO::PARAM_STR);
            }
        }
            try {
        $stmt->execute();
        } catch (\PDOException $e) {
            var_dump($this->bindables, array_values($this->bindables));
            var_dump($stmt); var_dump($e->getMEssage()); die();
        }
        return $stmt;
    }

    abstract public function __toString() : string;
}

