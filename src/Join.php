<?php

namespace Quibble\Query;

/**
 * A subclass used for building a join.
 */
class Join
{
    private string $table;

    private string $joinCondition;

    private string $type;

    private array $bindings = [];

    /**
     * Mark join as straight/inner.
     *
     * @param string|Quibble\Query\Select $table
     * @return self
     */
    public function inner(string|Select $table) : self
    {
        if (isset($this->type)) {
            throw new JoinException("The join style (inner, left, right, full) can only be set once.");
        }
        $this->table($table);
        return $this;
    }

    /**
     * Mark join as left.
     *
     * @param string|Quibble\Query\Select $table
     * @return self
     */
    public function left(string|Select $table) : self
    {
        if (isset($this->type)) {
            throw new JoinException("The join style (inner, left, right, full) can only be set once.");
        }
        $this->type = 'LEFT';
        $this->table($table);
        return $this;
    }

    /**
     * Mark join as right.
     *
     * @param string|Quibble\Query\Select $table
     * @return self
     */
    public function right(string|Select $table) : self
    {
        if (isset($this->type)) {
            throw new JoinException("The join style (inner, left, right, full) can only be set once.");
        }
        $this->type = 'RIGHT';
        $this->table($table);
        return $this;
    }

    /**
     * Mark join as full.
     *
     * @param string|Quibble\Query\Select $table
     * @return self
     */
    public function full(string|Select $table) : self
    {
        if (isset($this->type)) {
            throw new JoinException("The join style (inner, left, right, full) can only be set once.");
        }
        $this->type = 'FULL';
        $this->table($table);
        return $this;
    }

    /**
     * Mark join as USING($field).
     *
     * @param string $field
     * @return self
     */
    public function using(string $field) : self
    {
        $this->joinCondition = "USING($field)";
        return $this;
    }

    /**
     * Mark join as ON field1 = field2.
     *
     * @param string $on
     * @param mixed ...$bindables E.g. ON t1.f1 = ?
     * @return self
     */
    public function on(string $on, mixed ...$bindables) : self
    {
        $this->joinCondition = "ON $on";
        if ($bindables) {
            $this->bindings = array_merge($this->bindings, $bindables);
        }
        return $this;
    }

    /**
     * Get bindings supplied for the JOIN ON condition.
     *
     * @return array|null
     */
    public function getBindings() :? array
    {
        return $this->bindings ?? null;
    }

    /**
     * @return string
     * @throws Quibble\Query\JoinException if the join was not fully built.
     */
    public function __toString() : string
    {
        if (!isset($this->joinCondition)) {
            throw new JoinException("joinCondition must be set; call either `using` or `on`.");
        }
        return preg_replace("@\s+@", ' ', sprintf(
            '%s JOIN %s %s',
            $this->type ?? '',
            $this->table,
            $this->joinCondition
        ));
    }

    /**
     * Internal helper to store the table to be joined on.
     *
     * @param string|Quibble\Query\Select $table
     * @return void
     */
    private function table(string|Select $table) : void
    {
        if (is_object($table)) {
            $bindings = $table->getBindings();
            $this->bindings = array_merge($bindings, $this->bindings);
            $table = "$table";
        }
        $this->table = $table;
    }
}

