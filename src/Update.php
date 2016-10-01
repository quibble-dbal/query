<?php

namespace Quibble\Query;

use PDO;
use PDOException;
use Quibble\Dabble\SqlException;

class Update extends Builder
{
    use Where;

    /**
     * Execute the update statement. The first argument is a hash of key/value
     * pairs to update. Optional second argument are driver-specific options.
     *
     * @param array $set
     * @param array $driver_options
     * @return bool True on success.
     * @throws Quibble\Query\UpdateException if nothing was updated and
     *  PDO::ERRMODE_EXCEPTION is set.
     * @throws Quibble\Dabble\SqlException if the SQL contains an error and
     *  PDO::ERRMODE_EXCEPTION is set.
     */
    public function execute(array $set, array $driver_options = []) : bool
    {
        $error = false;
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $this->bindables['values'] = $set;
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
            throw new UpdateException($msg);
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
        $modifiers = [];
        foreach (array_keys($this->bindables['values']) as $field) {
            if (!is_numeric($field)) {
                $modifiers[] = "$field = ?";
            }
        }
        return sprintf(
            "UPDATE %s SET %s WHERE %s",
            $this->tables[0],
            implode(', ', $modifiers),
            implode(' ', $this->wheres)
        );
    }
}

