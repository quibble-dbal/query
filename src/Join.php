<?php

namespace Quibble\Query;

class Join
{
    private string $table;

    private string $joinCondition;

    private string $type;

    private array $bindings = [];

    public function inner(string|Select $table) : self
    {
        if (isset($this->type)) {
            throw new JoinException("The join style (inner, left, right, full) can only be set once.");
        }
        $this->type = 'INNER';
        $this->table($table);
        return $this;
    }

    public function left(string|Select $table) : self
    {
        $this->type = 'LEFT';
        $this->table($table);
        return $this;
    }

    public function right(string|Select $table) : self
    {
        $this->type = 'RIGHT';
        $this->table($table);
        return $this;
    }

    public function full(string|Select $table) : self
    {
        $this->type = 'FULL';
        $this->table($table);
        return $this;
    }

    public function using(string $field) : self
    {
        $this->joinCondition = "USING($field)";
        return $this;
    }

    public function on(string $on, ...$bindables) : self
    {
        $this->joinCondition = "ON $on";
        if ($bindables) {
            $this->bindings = array_merge($this->bindings, $bindables);
        }
        return $this;
    }

    public function getBindings() :? array
    {
        return $this->bindings ?? null;
    }

    public function __toString() : string
    {
        if (!isset($this->joinCondition)) {
            throw new JoinException("joinCondition must be set; call either `using` or `on`.");
        }
        return preg_replace("@\s+@", ' ', sprintf(
            '%s JOIN %s %s',
            $this->type,
            $this->table,
            $this->joinCondition
        ));
    }

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

