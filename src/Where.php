<?php

namespace Quibble\Query;

trait Where
{
    protected $wheres = [];

    public function where($sql, ...$bindables) : Builder
    {
        if ($bindables) {
            $sql = $this->appendBindings('where', $sql, $bindables);
        }
        $this->wheres[] = $sql;
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

    public function in($field, array $values) : string
    {
        $sql = "$field IN (";
        $sql .= implode(', ', array_fill(0, count($values), '?'));
        $sql .= ')';
        $sql = $this->appendBindings('where', $sql, array_values($values));
        return $sql;
    }

    public function notIn($field, array $values) : string
    {
        return $this->in("$field NOT", $values);
    }
}

