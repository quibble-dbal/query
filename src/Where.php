<?php

namespace Quibble\Query;

trait Where
{
    protected $wheres = [];

    public function where(string|callable $sql, mixed ...$bindables) : self
    {
        $sql = $this->checkGroup($sql);
        if ($bindables) {
            $sql = $this->appendBindings('where', $sql, $bindables);
        }
        $this->wheres[] = $sql;
        return $this;
    }
    
    public function orWhere(string|callable $sql, mixed ...$bindables) : self
    {
        $sql = $this->checkGroup($sql);
        if ($bindables) {
            $sql = $this->appendBindings('where', $sql, $bindables);
        }
        $this->wheres[] = [$sql];
        return $this;
    }

    protected function checkGroup(string|callable $sql) : string
    {
        if (is_callable($sql)) {
            $group = new Group($this->adapter);
            $sql($group);
            $sql = $this->appendBindings(
                'where',
                "$group",
                $group->getBindings()
            );
        }
        return $sql;
    }

    protected function recursiveImplode(string $carry, string|array $item) : string
    {
        static $condition = 'AND';
        if (is_array($item)) {
            $condition = $condition == 'AND' ? 'OR' : 'OR';
            $item = array_reduce($item, [$this, 'recursiveImplode'], '');
        }
        return strlen($carry) ? "($carry $condition $item)" : "($item)";
    }
}

