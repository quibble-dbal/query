<?php

namespace Quibble\Query;

use PDO;
use PDOException;

class Update extends Builder
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

    public function execute(array $set) : bool
    {
        $error = false;
        $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
        $this->bindables = array_merge($set, $this->bindables);
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
                    .implode(', ', $set).")";
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
        foreach (array_keys($this->bindables) as $field) {
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

