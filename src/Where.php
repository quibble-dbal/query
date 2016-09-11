<?php

namespace Quibble\Query;

trait Where
{
    protected $wheres = [];

    public function where($sql, ...$bindables) : Builder
    {
        $this->wheres[] = $sql;
        $this->bindables = array_merge($this->bindables, $bindables);
        return $this;
    }
    
    public function andWhere($sql, ...$bindables) : Builder
    {
        if (!$this->wheres) {
            return $this->where($sql, ...$bindables);
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

    public function in($field, array $values) : Builder
    {
        $sql = "$field IN (";
        $sql .= implode(', ', array_fill(0, count($values), '?'));
        $sql .= ')';
        return $this->where($sql, ...array_values($values));
    }
}

