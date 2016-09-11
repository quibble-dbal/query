<?php

namespace Quibble\Query;

use PDO;
use PDOException;

class Delete extends Builder
{
    use Where;

    private $values = [];

    public function __construct(PDO $adapter, $table, array $init = [])
    {
        if (is_array($table)) {
            $table = array_shift($table);
        }
        parent::__construct($adapter, $table, $init);
    }

    public function execute() : bool
    {
        $error = false;
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $result = false;
        try {
            $stmt = $this->getStatement();
            $result = $stmt->execute(array_values($this->bindables));
            if ($affectedRows = $stmt->rowCount() and $affectedRows) {
                return true;
            }
            if ($errmode == PDO::ERRMODE_EXCEPTION) {
                $info = $stmt->errorInfo();
                $msg = "{$info[0]} / {$info[1]}: {$info[2]} - $this ("
                    .implode(', ', $this->bindables).")";
                throw new DeleteException($msg);
            } else {
                return false;
            }
        } catch (PDOException $e) {
            $error = new DeleteException(
                "$this (".implode(', ', $this->bindables).")",
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

