<?php

namespace Quibble\Query;

use PDO;
use PDOException;
use Quibble\Dabble\SqlException;

class Delete extends Builder
{
    use Where;

    public function __construct(PDO $adapter, string $table)
    {
        parent::__construct($adapter);
        $this->tables = [$table];
    }

    /**
     * Execute the delete query.
     *
     * @param array $driver_options Optional driver-specific options.
     * @return bool True on success, else false.
     * @throws Quibble\Query\DeleteException if nothing was deleted and
     *  PDO::ERRMODE_EXCEPTION is set.
     * @throws Quibble\Dabble\SqlException if the query contains an error and
     *  PDO::ERRMODE_EXCEPTION is set.
     */
    public function execute(array $driver_options = []) : bool
    {
        $error = false;
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $result = false;
        $stmt = $this->getExecutedStatement($driver_options);
        if (!$stmt) {
            return false;
        }
        if ($affectedRows = $stmt->rowCount() and $affectedRows) {
            return true;
        }
        if ($errmode == PDO::ERRMODE_EXCEPTION) {
            $info = $stmt->errorInfo();
            $msg = "{$info[0]} / {$info[1]}: {$info[2]} - $this ("
                .implode(', ', $this->getBindings()).")";
            throw new DeleteException($msg);
        } else {
            return false;
        }
        return true;
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return sprintf(
            "DELETE FROM %s WHERE %s",
            $this->tables[0],
            implode(' ', $this->wheres)
        );
    }
}

