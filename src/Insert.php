<?php

namespace Quibble\Query;

use PDO;
use PDOException;
use Quibble\Dabble\SqlException;

class Insert extends Builder
{
    public function __construct(PDO $adapter, $table)
    {
        if (is_array($table)) {
            $table = array_shift($table);
        }
        parent::__construct($adapter, $table);
    }

    public function execute(array ...$sets) : bool
    {
        $error = false;
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $result = false;
        $res = 0;
        $this->bindables['values'] = $sets[0];
        try {
            $stmt = $this->getStatement();
            if (!$stmt) {
                return false;
            }
            foreach ($sets as $set) {
                $result = $stmt->execute(array_values($set));
                if ($affectedRows = $stmt->rowCount() and $affectedRows) {
                    $res += $affectedRows;
                    continue;
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
        } catch (PDOException $e) {
            if (!isset($set)) {
                $error = new SqlException("$this", SqlException::PREPARATION, $e);
            } else {
                $error = new InsertException(
                    "$this (".implode(', ', $set).")",
                    null,
                    $e
                );
            }
        }
        if (!$res && !$error) {
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
        $bindings = $this->bindables['values'];
        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->tables[0],
            implode(', ', array_keys($bindings)),
            implode(', ', array_fill(0, count($bindings), '?'))
        );
    }
}

