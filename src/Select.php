<?php

namespace Quibble\Query;

use PDO;
use PDOStatement;

class Select extends Builder
{
    use Where;
    use Limit;

    private $decorators = [];

    public function addDecorator($field, $class, ...$ctor_args) : Builder
    {
        $this->decorators[$field] = compact('class', 'ctor_args');
        return $this;
    }

    public function join($table, $style = '', ...$bindables) : Builder
    {
        $this->reset();
        $this->tables = array_merge(
            $this->tables,
            [sprintf('% JOIN %s', $style, $table)]
        );
        return $this;
    }

    public function leftJoin($table, ...$bindables) : Builder
    {
        return $this->join($table, 'LEFT', ...$bindables);
    }

    public function rightJoin($table, ...$bindables) : Builder
    {
        return $this->join($table, 'RIGHT', ...$bindables);
    }

    public function outerJoin($table, ...$bindables) : Builder
    {
        return $this->join($table, 'OUTER', ...$bindables);
    }

    public function fullOuterJoin($table, ...$bindables) : Builder
    {
        return $this->join($table, 'FULL OUTER', ...$bindables);
    }

    public function select(...$fields) : Builder
    {
        $this->reset();
        if ($this->fields == ['*']) {
            $this->fields = $fields;
        } else {
            $this->fields = array_unique(array_merge($this->fields, $fields));
        }
        return $this;
    }

    public function __toString() : string
    {
        return sprintf(
            'SELECT %s FROM %s%s%s%s%s%s',
            implode(', ', $this->fields),
            implode(' ', $this->tables),
            $this->wheres ? ' WHERE '.implode(' ', $this->wheres) : '',
            $this->group ? ' GROUP BY '.$this->group : '',
            $this->orders ? ' ORDER BY '.implode(', ', $this->orders) : '',
            isset($this->limit) ? sprintf(' LIMIT %d', $this->limit) : '',
            isset($this->offset) ? sprintf(' OFFSET %d', $this->offset) : ''
        );
    }

    public function fetch(...$args) : array
    {
        $stmt = $this->getExecutedStatement();
        return $this->applyDecorators($stmt->fetch(...$args));
    }

    public function fetchAll(...$args) : array
    {
        $stmt = $this->getExecutedStatement();
        return array_map([$this, 'applyDecorators'], $stmt->fetchAll(...$args));
    }

    public function fetchColumn(int $column_number = 0, $field = null)
    {
        $stmt = $this->getExecutedStatement();
        $column = $stmt->fetchColumn($column_number);
        if (isset($field)) {
            $this->applyDecorator($column, $field);
        }
        return $column;
    }

    private function applyDecorators(array $row) : array
    {
        array_walk($row, [$this, 'applyDecorator']);
        return $row;
    }

    private function applyDecorator(&$value, string $field)
    {
        foreach ($this->decorators as $name => $decorator) {
            if (is_callable($name)) {
                $value = $name($value);
            }
            if ($name == $field) {
                extract($decorator);
                array_unshift($ctor_args, $value);
                $value = new $class(...$ctor_args);
            }
        }
    }
}

