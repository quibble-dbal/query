<?php

namespace Quibble\Query;

class Join
{
    private string $table;

    private string $joinCondition;

    private string $direction;

    public function table(string $table) : self
    {
        $this->table = $table;
        return $this;
    }

    public function using(string $field) : self
    {
        $this->joinCondition = "USING($field)";
        return $this;
    }

    public function on(string $on) : self
    {
        $this->joinCondition = "ON $on";
        return $this;
    }

    public function __toString() : string
    {
        if (!isset($this->joinCondition)) {
            throw new JoinException("joinCondition must be set; call either `using` or `on`.");
        }
        return preg_replace("@\s+@", ' ', sprintf(
            '%s JOIN %s %s',
            $this->direction ?? '',
            $this->table,
            $this->joinCondition
        ));
    }
}

