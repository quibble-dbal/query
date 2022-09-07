<?php

namespace Quibble\Query;

use DomainException;
use PDOStatement;

/**
 * A "group" statement. In `quibble/query`, the concept of "groups" is basically
 * an isolated sub-statement, most commonly use when supplying `where` or
 * `orWhere` with a callback.
 */
class Group extends Builder
{
    use Where;
    use Bindable;

    /**
     * @return string
     */
    public function __toString() : string
    {
        return array_reduce($this->wheres, [$this, 'recursiveImplode'], '');
    }

    /**
     * Added because this is illegal; a group is an incomplete statement by
     * definition.
     *
     * @param array $driver_options
     * @return PDOStatement|null
     * @throws DomainException This is always thrown.
     */
    public function getStatement(array $driver_options = []) :? PDOStatement
    {
        throw new DomainException("Cannot get a statement for a group.");
    }

    /**
     * Added because this is illegal; a group is an incomplete statement by
     * definition.
     *
     * @param array $driver_options
     * @return PDOStatement|null
     * @throws DomainException This is always thrown.
     */
    public function getExecutedStatement(array $driver_options = []) :? PDOStatement
    {
        throw new DomainException("Cannot get a statement for a group.");
    }

    /**
     * Generate an `IN (...)` clause based on an array of known values.
     *
     * @param string $field The field to check on.
     * @param array $values The values to check for.
     * @return self
     */
    public function in(string $field, array $values) : self
    {
        $sql = "$field IN (";
        $sql .= implode(', ', array_fill(0, count($values), '?'));
        $sql .= ')';
        return $this->where($sql, ...array_values($values));
    }

    /**
     * Generate a `NOT IN (...)` clause based on an array of known values.
     *
     * @param string $field The field to check on.
     * @param array $values The values to check for.
     * @return self
     */
    public function notIn(string $field, array $values) : self
    {
        return $this->in("$field NOT", $values);
    }
}

