<?php

namespace Quibble\Query;

use PDO;
use PDOException;
use Quibble\Dabble\SqlException;

class Insert extends Builder
{
    /**
     * Execute the insert query on one or more supplied sets. If you need to
     * specify $driver_options, you may do so with the final argument.
     *
     * @param array ...$sets A hash of data with key/value pairs.
     * @return bool True on success.
     * @throws Quibble\Query\InsertException if any of the sets couldn't be
     *  inserted and PDO::ERRMODE_EXCEPTION is set.
     * @throws Quibble\Dabble\SqlException if the query contains an error and
     *  PDO::ERRMODE_EXCEPTION is set.
     */
    public function execute(array ...$sets) : bool
    {
        $error = false;
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $result = false;
        $res = 0;
        $driver_options = [];
        if (count($sets) > 1) {
            $keys = array_keys($set[0]);
            $lastArg = end($set);
            if (array_keys($lastArg) != $keys) {
                $driver_options = $lastArg;
                array_pop($sets);
            }
        }
        foreach ($sets as $set) {
            $this->bindables['values'] = $set;
            if ($stmt = $this->getExecutedStatement($driver_options)
                and $affectedRows = $stmt->rowCount()
                and $affectedRows
            ) {
                $res += $affectedRows;
                if ($stmt) {
                    continue;
                }
            }
            if ($errmode == PDO::ERRMODE_EXCEPTION) {
                $info = $stmt->errorInfo();
                $msg = "{$info[0]} / {$info[1]}: {$info[2]} - $this ("
                    .implode(', ', $set).")";
                throw new InsertException($msg);
            }
        }
        if (!$res) {
            return false;
        }
        return true;
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        $bindings = $this->bindables['values'];
        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->tables[0],
            implode(', ', array_keys($bindings)),
            implode(', ', array_fill(0, count($bindings), '?'))
        );
    }
}

