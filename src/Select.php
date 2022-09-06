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

    /**
     * @param array $driver_options
     * @return void
     */
    public function setDriverOptions(array $driver_options = []) : void
    {
        $this->driverOptions = $driver_options;
    }

    /**
     * @string $table
     * @return Quibble\Query\Builder
     */
    public function andFrom(string $table) : self
    {
        $this->tables[] = ", $table";
        return $this;
    }

    /**
     * @param callable $callback
     * @param string $on
     * @param string $style `'left'` etc.
     * @param mixed ...$bindables
     * @return Quibble\Query\Builder
     */
    public function join(callable $callback) : self
    {
        /*
        if (is_object($table) && $table instanceof Select) {
            $bindables = array_merge($table->getBindings(), $bindables);
            $table = "$table";
        }
        $table = $this->appendBindings(
            'join',
            sprintf(preg_match("@^\w+$@", $on) ? '%s JOIN %s USING(%s)' : '%s JOIN %s ON %s', $style, $table, $on),
            $bindables
        );
        */
        $join = new Join;
        $callback($join);
        $this->tables[] = $join;
        return $this;
    }

    /**
     * @param string|Quibble\Query\Select $table
     * @param string $on
     * @param mixed ...$bindables
     * @return Quibble\Query\Builder
     */
    public function leftJoin($table, string $on, ...$bindables) : self
    {
        return $this->join($table, $on, 'LEFT', ...$bindables);
    }

    /**
     * @param string|Quibble\Query\Select $table
     * @param string $on
     * @param mixed ...$bindables
     * @return Quibble\Query\Builder
     */
    public function rightJoin($table, string $on, ...$bindables) : self
    {
        return $this->join($table, $on, 'RIGHT', ...$bindables);
    }

    /**
     * @param string|Quibble\Query\Select $table
     * @param string $on
     * @param mixed ...$bindables
     * @return Quibble\Query\Builder
     */
    public function outerJoin($table, string $on, ...$bindables) : self
    {
        return $this->join($table, $on, 'OUTER', ...$bindables);
    }

    /**
     * @param string|Quibble\Query\Select $table
     * @param string $on
     * @param mixed ...$bindables
     * @return Quibble\Query\Builder
     */
    public function fullOuterJoin($table, string $on, ...$bindables) : self
    {
        return $this->join($table, $on, 'FULL OUTER', ...$bindables);
    }

    /**
     * @param string $sql
     * @return Quibble\Query\Builder
     */
    public function orderBy(string $sql) : self
    {
        $this->order = $sql;
        return $this;
    }

    /**
     * @param string $sql
     * @return Quibble\Query\Builder
     */
    public function groupBy(string $sql) : self
    {
        $this->group = $sql;
        return $this;
    }

    /**
     * @param string ...$fields
     * @return Quibble\Query\Builder
     */
    public function select(string ...$fields) : self
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * @param string $sql
     * @return Quibble\Query\Builder
     */
    public function having($sql, ...$bindables) : self
    {
        $this->havings = $this->appendBindings('having', $sql, $bindables);
        return $this;
    }

    /**
     * Allows you to union with another builder.
     *
     * @param Quibble\Query\Select $query
     * @param string $style E.g. `'all'`
     * @return Quibble\Query\Builder
     */
    public function union(Select $query, string $style = '') : self
    {
        $this->unions[] = compact('style', 'query');
        return $this;
    }

    /**
     * Shorthand for `union($query, 'ALL')`.
     *
     * @param Quibble\Query\Select $query
     * @return Quibble\Query\Builder
     * @see Quibble\Query\Select::union
     */
    public function unionAll(Select $query) : self
    {
        return $this->union($query, 'ALL');
    }

    /**
     * Generates SQL statement suitable for `PDO::prepare`.
     *
     * @return string
     */
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

    /**
     * Proxy to `PDOStatement::fetch`.
     *
     * @param mixed ...$args
     * @return mixed
     * @throws Quibble\Query\SelectException if no results and error mode is set
     *  to `PDO::ERRMODE_EXCEPTION`.
     */
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

    /**
     * Proxy to `PDOStatement::fetchAll`.
     *
     * @param mixed ...$args
     * @return array|null
     * @throws Quibble\Query\SelectException if no results and error mode is set
     *  to `PDO::ERRMODE_EXCEPTION`.
     */
    public function fetchAll(...$args) :? array
    {
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $result = null;
        $stmt = $this->getExecutedStatement($this->driverOptions);
        if (!$stmt) {
            return null;
        }
        if ((false !== ($result = $stmt->fetchAll(...$args))) and $result) {
            return $result;
        } elseif ($errmode == PDO::ERRMODE_EXCEPTION) {
            throw new SelectException("$this (".implode(', ', $this->getBindings()).")");
        } else {
            return $result;
        }
    }

    /**
     * Proxy to `PDOStatement::fetchColumn`.
     *
     * @param int $column_number Defaults to null.
     * @return mixed The column's value, or `false` if nothing was found.
     */
    public function fetchColumn(int $column_number = 0)
    {
        $stmt = $this->getExecutedStatement();
        if (!$stmt) {
            return false;
        }
        return $stmt->fetchColumn($column_number);
    }

    /**
     * Proxy to `PDOStatement::fetchObject`.
     *
     * @param string $class_name Defaults to `stdClass`.
     * @param array $ctor_args Optional constructor arguments.
     * @return mixed
     * @throws Quibble\Query\SelectException if no results and error mode is set
     *  to `PDO::ERRMODE_EXCEPTION`.
     */
    public function fetchObject(string $class_name = 'stdClass', array $ctor_args = [])
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

    /**
     * Count results in built select statement.
     *
     * @param string $what What to count, defaults to `'*'`.
     * @return int
     */
    public function count(string $what = '*') : int
    {
        return (int)$this->select("COUNT($what)")->fetchColumn();
    }

    /**
     * Like `fetchAll`, only returns a Generator.
     *
     * @param mixed ...$args See `fetchAll`.
     * @return Generator
     * @throws Quibble\Query\SelectException if no results and error mode is set
     *  to PDO::ERRMODE_EXCEPTION.
     * @see Quibble\Query\Select::fetchAll()
     */
    public function generate(...$args) : Generator
    {
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $found = 0;
        if ($stmt = $this->getExecutedStatement()) {
            while (false !== ($row = $stmt->fetch(...$args))) {
                $found++;
                yield $row;
            }
        }
        if ($found == 0 && $errmode == PDO::ERRMODE_EXCEPTION) {
            throw new SelectException("$this (".implode(',', $this->getBindings()).")");
        }
    }

    /**
     * Indicates this query will be run as a subquery. The SQL will be wrapped
     * in parentheses and optionally aliased at runtime. To turn subqueries off
     * again, pass null as the alias.
     *
     * @param string|null The alias to use. If you don't need one, leave it
     *  empty.
     * @return Quibble\Query\Builder
     */
    public function asSubquery(string $alias = null)
    {
        $this->isSubquery = isset($alias) ? $alias : true;
        return $this;
    }
}

