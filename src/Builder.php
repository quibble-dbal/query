<?php

namespace Quibble\Query;

use Quibble\Dabble\SqlException;
use PDO;
use PDOStatement;
use PDOException;

abstract class Builder
{
    /**
     * An instance of a PDO resource.
     *
     * @var PDO
     */
    protected $adapter;

    /**
     * An array of table(s) this query runs on.
     *
     * @var array
     */
    protected $tables = [];

    /**
     * A hash of bindables. Internally we store each query part in subarrays so
     * we can later bind in the right order and it doesn't matter in which order
     * the building methods are called.
     *
     * @var array
     */
    protected $bindables = ['values' => [], 'where' => [], 'options' => []];

    /**
     * Hash of cached statements.
     *
     * @var array
     */
    private $statements = [];

    /**
     * Construct a query builder.
     *
     * @param PDO $adapter The database connection.
     * @param string $table The base table to work on. A `Select` query can add
     *  more tables using `andFrom`.
     */
    public function __construct(PDO $adapter, $table)
    {
        $this->adapter = $adapter;
        if (is_string($table)) {
            $table = [$table];
        }
        $this->tables = $table;
    }

    /**
     * Returns a flattened array of bindings. This makes an educated attempt to
     * order the bindings correctly.
     *
     * @return array
     */
    public function getBindings() : array
    {
        return array_merge(
            $this->bindables['values'],
            $this->bindables['where'],
            $this->bindables['options']
        );
    }

    /**
     * Proxy to `__toString()` to offer an API consistent with similar packages.
     *
     * @return string
     */
    public function toSql() : string
    {
        return $this->__toString();
    }

    /**
     * Get the statement as it is currently built. Will use a cached version if
     * possible.
     *
     * @param array $driver_params Optional driver-specific parameters.
     * @return PDOStatement|false On success, the statement. If error mode isn't
     *  set to PDO::ERRMODE_EXCEPTION, false on failure.
     * @throws Quibble\Query\SqlException if the statement could not be built
     *  and error mode is set to PDO::ERRMODE_EXCEPTION.
     * @see PDO::prepare
     */
    public function getStatement($driver_params = null) : PDOStatement
    {
        $sql = $this->__toString();
        if (!isset($this->statements[$sql])) {
            try {
                $this->statements[$sql] = $this->adapter->prepare(
                    $sql,
                    $driver_params
                );
                if (!$this->statements[$sql]) {
                    unset($this->statements[$sql]);
                    return false;
                }
            } catch (PDOException $e) {
                throw new SqlException(
                    $this->__toString(),
                    SqlException::PREPARATION,
                    $e
                );
            }
        }
        return $this->statements[$sql];
    }

    /**
     * Get the executed statement.
     *
     * @param array $driver_params Optional driver-specific parameters.
     * @return PDOStatement|false On success, the statement. If error mode isn't
     *  set to PDO::ERRMODE_EXCEPTION, false on failure.
     * @throws Quibble\Query\SqlException if the statement could not be built
     *  and error mode is set to PDO::ERRMODE_EXCEPTION.
     * @see Quibble\Query\Builder::getStatement
     */
    public function getExecutedStatement($driver_params = null) : PDOStatement
    {
        $stmt = $this->getStatement();
        $stmt->execute($this->getBindings());
        return $stmt;
    }

    /**
     * Queries must implement `__toString`. This simply returns the SQL as it
     * should be passed to `PDO::prepare`.
     *
     * @return string
     */
    abstract public function __toString() : string;
}

