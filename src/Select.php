<?php

namespace Quibble\Query;

use PDO;
use PDOStatement;
use Generator;

class Select extends Builder
{
    use Where;
    use Limit;

    protected $fields = ['*'];
    protected $group = null;
    protected $havings = null;
    protected $order = null;
    protected $unions = [];
    protected $driverOptions = [];
    protected $isSubquery = false;

    public function setDriverOptions(array $driver_options = [])
    {
        $this->driverOptions = $driver_options;
    }

    public function andFrom($table) : Builder
    {
        $this->tables[] = ", $table";
        return $this;
    }

    public function join($table, string $on, string $style = '', ...$bindables) : Builder
    {
        if (is_object($table) && $table instanceof Select) {
            $bindables = array_merge($table->getBindings(), $bindables);
            $table = "$table";
        }
        $table = $this->appendBindings(
            'join',
            sprintf('%s JOIN %s ON %s', $style, $table, $on),
            $bindables
        );
        $this->tables[] = $table;
        return $this;
    }

    public function leftJoin($table, string $on, ...$bindables) : Builder
    {
        return $this->join($table, $on, 'LEFT', ...$bindables);
    }

    public function rightJoin($table, string $on, ...$bindables) : Builder
    {
        return $this->join($table, $on, 'RIGHT', ...$bindables);
    }

    public function outerJoin($table, string $on, ...$bindables) : Builder
    {
        return $this->join($table, $on, 'OUTER', ...$bindables);
    }

    public function fullOuterJoin($table, string $on, ...$bindables) : Builder
    {
        return $this->join($table, $on, 'FULL OUTER', ...$bindables);
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
        $this->fields = $fields;
        return $this;
    }

    public function having($sql, ...$bindables) : Builder
    {
        $this->havings = $this->appendBindings('having', $sql, $bindables);
        return $this;
    }

    public function union(Select $query, $style = '') : Builder
    {
        $this->unions[] = compact('style', 'query');
        return $this;
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
        if ($this->isSubquery) {
            $sql = "($sql)";
            if (is_string($this->isSubquery)) {
                $sql .= " AS {$this->isSubquery}";
            }
        }
        return $sql;
    }

    public function fetch(...$args)
    {
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $stmt = $this->getExecutedStatement($this->driverOptions);
        if (!$stmt) {
            return false;
        }
        if (false !== ($result = $stmt->fetch(...$args))) {
            return $result;
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
        $stmt = $this->getExecutedStatement($this->driverOptions);
        if (!$stmt) {
            return false;
        }
        if ((false !== ($result = $stmt->fetchAll(...$args))) and $result) {
            return $result;
        } elseif ($errmode == PDO::ERRMODE_EXCEPTION) {
            throw new SelectException("$this (".implode(', ', $this->getBindings()).")");
        } else {
            return $result;
        }
    }

    public function fetchColumn(int $column_number = 0, $field = null)
    {
        $stmt = $this->getExecutedStatement();
        if (!$stmt) {
            return false;
        }
        return $stmt->fetchColumn($column_number);
    }

    public function fetchObject($class_name = 'stdClass', array $ctor_args = [])
    {
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $stmt = $this->getExecutedStatement();
        if (!$stmt) {
            return false;
        }
        if (false !== ($result = $stmt->fetchObject($class_name, $ctor_args))) {
            return $result;
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
        if ($stmt = $this->getExecutedStatement()) {
            while (false !== ($row = $stmt->fetch(...$args))) {
                yield $row;
            }
        }
    }

    /**
     * Indicates this query will be run as a subquery. The SQL will be wrapped
     * in parentheses and optionally aliased at runtime. To turn subqueries off
     * again, pass an empty string as the alias.
     *
     * @param string|null The alias to use. If you don't need one, leave it
     *  empty.
     * @return self
     */
    public function asSubquery(string $alias = null)
    {
        $this->isSubquery = isset($alias) ? $alias : true;
        return $this;
    }
}

