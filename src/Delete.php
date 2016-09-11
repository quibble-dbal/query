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

    public function execute(array ...$values) : bool
    {
        $error = false;
        foreach ($this->values as $set) {
            $this->bindables = $set;
            $result = false;
            try {
                $result = $this->getStatement()->execute(array_values($set));
            } catch (PDOException $e) {
                $error = new DeleteException(
                    "$this (".implode(', ', $set).")",
                    null,
                    $e
                );
            }
            if (!$result && !$error) {
                $error = new DeleteException("$this (".implode(', ', $set).")");
            }
        }
        if ($error) {
            $errmode = $this->adapter->getAttribute(PDO::ATTR_ERRMODE);
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
            $this->table,
            implode(', ', array_keys($this->bindables)),
            implode(', ', array_fill(0, count($this->bindables), '?'))
        );
    }
}

