<?php

namespace Quibble\Query;

use PDO;
use PDOException;

class Insert extends Builder
{
    private $values = [];

    public function __construct(PDO $adapter, $table, array $init = [])
    {
        if (is_array($table)) {
            $table = array_shift($table);
        }
        parent::__construct($adapter, $table, $init);
    }

    public function execute(array $set) : bool
    {
        $error = false;
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $this->bindables = $set;
        $result = false;
        try {
            $stmt = $this->getStatement();
            $result = $stmt->execute(array_values($set));
            if ($affectedRows = $stmt->rowCount() and $affectedRows) {
                return true;
            }
            if ($errmode == PDO::ERRMODE_EXCEPTION) {
                $info = $stmt->errorInfo();
                $msg = "{$info[0]} / {$info[1]}: {$info[2]} - $this ("
                    .implode(', ', $set).")";
                throw new InsertException($msg);
            } else {
                return false;
            }
        } catch (PDOException $e) {
            $error = new InsertException(
                "$this (".implode(', ', $set).")",
                null,
                $e
            );
        }
        if (!$result && !$error) {
            $error = new InsertException("$this (".implode(', ', $set).")");
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
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->tables[0],
            implode(', ', array_keys($this->bindables)),
            implode(', ', array_fill(0, count($this->bindables), '?'))
        );
    }
}

