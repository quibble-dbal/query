<?php

namespace Quibble\Query;

use PDO;
use PDOStatement;
use Generator;

class Select extends Builder
{
    use Where;
    use Limit;

    private $decorators = [];
    protected $fields = ['*'];
    protected $group = null;
    protected $havings = null;
    protected $order = null;
    protected $unions = [];

    public function addDecorator($field, $class, ...$ctor_args) : Builder
    {
        $this->decorators[$field] = compact('class', 'ctor_args');
        return $this;
    }

    public function join($table, $style = '', ...$bindables) : Builder
    {
        $table = $this->appendBindings('join', $table, $bindables);
        $this->tables = array_merge(
            $this->tables,
            [sprintf('%s JOIN %s', $style, $table)]
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

    public function orderBy($sql) : Builder
    {
        $this->order = $sql;
        return $this;
    }

    public function groupBy($sql) : Builder
    {
        $this->group = $sql;
        return $this;
    }

    public function select(...$fields) : Builder
    {
        if ($this->fields == ['*']) {
            $this->fields = $fields;
        } else {
            $this->fields = array_unique(array_merge($this->fields, $fields));
        }
        return $this;
    }

    public function having($sql, ...$bindables) : Builder
    {
        $this->havings = $this->appendBindings('having', $sql, $bindables);
        return $this;
    }

    public function union(Select $query, $style = '')
    {
        $this->unions[] = compact('style', 'query');
    }

    public function unionAll(Select $query)
    {
        return $this->union($query, 'ALL');
    }

    public function __toString() : string
    {
        $sql = sprintf(
            'SELECT %s FROM %s%s%s%s%s%s%s',
            implode(', ', $this->fields),
            implode(' ', $this->tables),
            $this->wheres ? ' WHERE '.implode(' ', $this->wheres) : '',
            $this->group ? ' GROUP BY '.$this->group : '',
            ($this->group && $this->havings) ? " HAVING {$this->havings} " : '',
            $this->order ? ' ORDER BY '.$this->order : '',
            isset($this->limit) ? sprintf(' LIMIT %d', $this->limit) : '',
            isset($this->offset) ? sprintf(' OFFSET %d', $this->offset) : ''
        );
        if ($this->unions) {
            foreach ($this->unions as $union) {
                extract($union);
                $sql .= " UNION $style $query";
                $this->appendBindings('having', $sql, $query->getBindings());
            }
        }
        return $sql;
    }

    public function fetch(...$args)
    {
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        if ((false !== ($stmt = $this->getExecutedStatement()))
            and (false !== ($result = $stmt->fetch(...$args)))
        ) {
            return $this->applyDecorators($result);
        } elseif ($errmode == PDO::ERRMODE_EXCEPTION) {
            throw new SelectException("$this (".implode(', ', $this->getBindings()).")");
        } else {
            return false;
        }
    }

    public function fetchAll(...$args)
    {
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $result = false;
        if ((false !== ($stmt = $this->getExecutedStatement()))
            and (false !== ($result = $stmt->fetchAll(...$args)))
            and $result
        ) {
            return array_map([$this, 'applyDecorators'], $result);
        } elseif ($errmode == PDO::ERRMODE_EXCEPTION) {
            throw new SelectException("$this (".implode(', ', $this->getBindings()).")");
        } else {
            return $result;
        }
    }

    public function fetchColumn(int $column_number = 0, $field = null)
    {
        if (false === ($stmt = $this->getExecutedStatement())) {
            return false;
        }
        $column = $stmt->fetchColumn($column_number);
        if (isset($field)) {
            $this->applyDecorator($column, $field);
        }
        return $column;
    }

    public function fetchObject($class_name = 'stdClass', array $ctor_args = [])
    {
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        if ((false !== ($stmt = $this->getExecutedStatement()))
            and (false !== ($result = $stmt->fetchObject($class_name, $ctor_args)))
        ) {
            return $this->applyDecorators($result);
        } elseif ($errmode == PDO::ERRMODE_EXCEPTION) {
            throw new SelectException("$this (".implode(', ', $this->getBindings()).")");
        } else {
            return false;
        }
    }

    public function count($what = '*') : int
    {
        return (int)$this->select("COUNT($what)")->fetchColumn();
    }

    public function generate(...$args) : Generator
    {
        $stmt = $this->getExecutedStatement();
        while (false !== ($row = $stmt->fetch(...$args))) {
            yield $this->applyDecorators($row);
        }
    }

    private function applyDecorators($row)
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

