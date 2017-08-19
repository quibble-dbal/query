<?php

namespace Quibble\Query;

use Quibble\Dabble\SqlException;
use Quibble\Dabble\Raw;
use PDO;
use PDOStatement;
use PDOException;

abstract class Builder
{
    use Bindable;

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
     * Hash of cached statements.
     *
     * @var array
     */
    protected static $statements = [];

    /**
     * Construct a query builder.
     *
     * @param PDO $adapter The database connection.
     * @param string|Quibble\Query\Builder $table The base table to work on. A
     *  `Select` query can add more tables using `andFrom` or one of the
     *  `joinNNN` methods.
     */
    public function __construct(PDO $adapter, $table)
    {
        $this->adapter = $adapter;
        if ($table instanceof Builder) {
            $table = $this->appendBindings('values', "$table", $table->getBindings());
        }
        $this->tables = [$table];
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
     * @param array $driver_options Optional driver-specific parameters.
     * @return PDOStatement|false On success, the statement. If error mode isn't
     *  set to PDO::ERRMODE_EXCEPTION, false on failure.
     * @throws Quibble\Query\SqlException if the statement could not be built
     *  and error mode is set to PDO::ERRMODE_EXCEPTION.
     * @see PDO::prepare
     */
    public function getStatement(array $driver_options = [])
    {
        $sql = $this->__toString();
        if (!isset(static::$statements[$sql])) {
            try {
                static::$statements[$sql] = $this->adapter->prepare(
                    $sql,
                    $driver_options
                );
                if (!static::$statements[$sql]) {
                    unset(static::$statements[$sql]);
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
        return static::$statements[$sql];
    }

    /**
     * Get the executed statement.
     *
     * @param array $driver_options Optional driver-specific parameters.
     * @return PDOStatement|false On success, the statement. If error mode isn't
     *  set to PDO::ERRMODE_EXCEPTION, false on failure.
     * @throws Quibble\Query\SqlException if the statement could not be built
     *  and error mode is set to PDO::ERRMODE_EXCEPTION.
     * @see Quibble\Query\Builder::getStatement
     */
    public function getExecutedStatement(array $driver_options = [])
    {
        $stmt = $this->getStatement($driver_options);
        if (!$stmt) {
            return false;
        }
        $this->applyBindings($stmt)->execute();
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

