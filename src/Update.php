<?php

namespace Quibble\Query;

use PDO;
use PDOException;

class Update extends Builder
{
    use Where;

    public function __construct(PDO $adapter, $table)
    {
        if (is_array($table)) {
            $table = array_shift($table);
        }
        parent::__construct($adapter, $table);
    }

    public function execute(array $set) : bool
    {
        $error = false;
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $this->bindables['values'] = $set;
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
                throw new UpdateException($msg);
            } else {
                return false;
            }
        } catch (PDOException $e) {
            $error = new UpdateException(
                "$this (".implode(', ', $set).")",
                null,
                $e
            );
        }
        if (!$result && !$error) {
            $error = new UpdateException("$this (".implode(', ', $set).")");
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

