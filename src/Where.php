<?php

namespace Quibble\Query;

/**
 * A trait supplying the WHERE methods.
 */
trait Where
{
    protected array $wheres = [];

    /**
     * Add a normal AND WHERE condition. If the $sql is supplied as a callable,
     * it is called with a $group parameter.
     *
     * @param string|callable $sql
     * @param mixed ...$bindables
     * @return self
     */
    public function where(string|callable $sql, mixed ...$bindables) : self
    {
        $sql = $this->checkGroup($sql);
        if ($bindables) {
            $sql = $this->appendBindings('where', $sql, $bindables);
        }
        $this->wheres[] = $sql;
        return $this;
    }
    
    /**
     * See `where`, only with an "OR" instead of "AND".
     *
     * @param string|callable $sql
     * @param mixed ...$bindables
     * @return self
     */
    public function orWhere(string|callable $sql, mixed ...$bindables) : self
    {
        $sql = $this->checkGroup($sql);
        if ($bindables) {
            $sql = $this->appendBindings('where', $sql, $bindables);
        }
        $this->wheres[] = [$sql];
        return $this;
    }

    /**
     * Internal helper to check (and run) for a grouping.
     *
     * @param string|callable $sql
     * @return string
     */
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

    /**
     * Recursively "implode" the where array to a single string.
     *
     * @param string $carry The SQL so far.
     * @param string|array $item
     * @return string
     */
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

