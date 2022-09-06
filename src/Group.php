<?php

namespace Quibble\Query;

use DomainException;
use PDOStatement;

class Group extends Builder
{
    use Where;
    use Bindable;

    public function __toString() : string
    {
        return array_reduce($this->wheres, [$this, 'recursiveImplode'], '');
    }

    public function getStatement(array $driver_options = []) :? PDOStatement
    {
        throw new DomainException("Cannot get a statement for a group.");
    }

    public function getExecutedStatement(array $driver_options = []) :? PDOStatement
    {
        throw new DomainException("Cannot get a statement for a group.");
    }

    public function in($field, array $values) : self
    {
        $sql = "$field IN (";
        $sql .= implode(', ', array_fill(0, count($values), '?'));
        $sql .= ')';
        return $this->where($sql, ...array_values($values));
    }

    public function notIn($field, array $values) : self
    {
        return $this->in("$field NOT", $values);
    }
}

