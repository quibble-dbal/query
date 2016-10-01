<?php

namespace Quibble\Query;

use PDO;
use PDOException;

class Delete extends Builder
{
    use Where;

    public function __construct(PDO $adapter, $table)
    {
        if (is_array($table)) {
            $table = array_shift($table);
        }
        parent::__construct($adapter, $table);
    }

    public function execute() : bool
    {
        $error = false;
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $result = false;
        try {
            $stmt = $this->getStatement();
            if (!$stmt) {
                return false;
            }
            $result = $stmt->execute($this->getBindings());
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
        } catch (PDOException $e) {
            $error = new DeleteException(
                "$this (".implode(', ', $this->getBindings()).")",
                null,
                $e
            );
        }
        if (!$result && !$error) {
            $error = new DeleteException("$this (".implode(', ', $set).")");
        }
        if ($error) {
            if ($errmode == PDO::ERRMODE_EXCEPTION) {
                throw $error;
            } else {
                return false;
            }
        }
        return true;
    }

    public function __toString() : string
    {
        return sprintf(
            "DELETE FROM %s WHERE %s",
            $this->tables[0],
            implode(' ', $this->wheres)
        );
    }
}

