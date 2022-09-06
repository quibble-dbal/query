<?php

namespace Quibble\Query;

use PDO;
use PDOStatement;
use Generator;

class Select extends Builder
{
    use Where;
    use Limit;

    protected array $fields = ['*'];

    protected string $group;

    protected array $havings = [];

    protected string $order;

    protected array $unions = [];

    protected array $driverOptions = [];

    protected bool $isSubquery = false;

    /**
     * Construct a query builder.
     *
     * @param PDO $adapter The database connection.
     * @param string|Quibble\Query\Select $table The base table to work on. A
     *  `Select` query can add more tables using `andFrom` or the `join` method.
     */
    public function __construct(PDO $adapter, string|Select $table)
    {
        parent::__construct($adapter);
        if ($table instanceof Builder) {
            $table = $this->appendBindings('values', "$table", $table->getBindings());
        }
        $this->tables = [$table];
    }

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
     * @return self
     */
    public function andFrom(string $table) : self
    {
        $that = clone $this;
        $that->tables[] = ", $table";
        return $that;
    }

    /**
     * @param callable $callback
     * @param string $on
     * @param string $style `'left'` etc.
     * @param mixed ...$bindables
     * @return self
     */
    public function join(callable $callback) : self
    {
        $that = clone $this;
        $join = $callback(new Join);
        if ($bindables = $join->getBindings()) {
            $join = $that->appendBindings(
                'join',
                "$join",
                $bindables
            );
        }
        $that->tables[] = $join;
        return $that;
    }

    /**
     * @param string $sql
     * @return self
     */
    public function orderBy(string $sql) : self
    {
        $that = clone $this;
        $that->order = $sql;
        return $that;
    }

    /**
     * @param string $sql
     * @return self
     */
    public function groupBy(string $sql) : self
    {
        $that = clone $this;
        $that->group = $sql;
        return $that;
    }

    /**
     * @param string ...$fields
     * @return self
     */
    public function fields(string ...$fields) : self
    {
        $that = clone $this;
        $that->fields = $fields;
        return $that;
    }

    /**
     * @param string $sql
     * @return self
     */
    public function having($sql, ...$bindables) : self
    {
        $that = clone $this;
        $that->havings = $that->appendBindings('having', $sql, $bindables);
        return $that;
    }

    /**
     * Allows you to union with another builder.
     *
     * @param Quibble\Query\Select $query
     * @param string $style E.g. `'all'`
     * @return self
     */
    public function union(Select $query, string $style = '') : self
    {
        $that = clone $this;
        $that->unions[] = compact('style', 'query');
        return $that;
    }

    /**
     * Shorthand for `union($query, 'ALL')`.
     *
     * @param Quibble\Query\Select $query
     * @return self
     * @see Quibble\Query\Select::union
     */
    public function unionAll(Select $query) : self
    {
        $that = clone $this;
        return $that->union($query, 'ALL');
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
            $this->wheres ? ' WHERE '.array_reduce($this->wheres, [$this, 'recursiveImplode'], '') : '',
            isset($this->group) ? ' GROUP BY '.$this->group : '',
            (isset($this->group) && $this->havings) ? " HAVING {$this->havings} " : '',
            isset($this->order) ? ' ORDER BY '.$this->order : '',
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
     * @return self
     */
    public function asSubquery(string $alias = null)
    {
        $this->isSubquery = isset($alias) ? $alias : true;
        return $this;
    }
}

