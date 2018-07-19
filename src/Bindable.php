<?php

namespace Quibble\Query;

use PDO;
use PDOStatement;

trait Bindable
{
    /**
     * A hash of bindables. Internally we store each query part in subarrays so
     * we can later bind in the right order and it doesn't matter in which order
     * the building methods are called.
     *
     * @var array
     */
    protected $bindables = ['values' => [], 'join' => [], 'where' => [], 'having' => []];

    /**
     * Returns a flattened array of bindings. This makes an educated attempt to
     * order the bindings correctly.
     *
     * @return array
     */
    public function getBindings() : array
    {
        return array_values(array_merge(
            $this->bindables['values'],
            $this->bindables['join'],
            $this->bindables['where'],
            $this->bindables['having']
        ));
    }
    
    /**
     * Internal helper to append bindings to the correct subkey. This also
     * replaces any binding where the value is an array with its "raw" value.
     *
     * @param string $key The subkey to bind to.
     * @param string $sql The SQL snippet we want to bind to.
     * @param array $bindables An array of bindables.
     * @return string The modified SQL.
     */
    protected function appendBindings(string $key, string $sql, array $bindables) : string
    {
        $parts = explode('?', $sql);
        foreach (array_values($bindables) as $i => $bindable) {
            if (is_array($bindable)) {
                continue;
            } elseif ($bindable instanceof Select) {
                $parts[$i] .= "$bindable";
                $parts[$i] = $this->appendBindings(
                    $key,
                    $parts[$i],
                    $bindable->getBindings()
                );
            } else {
                $parts[$i] .= '?';
                $this->bindables[$key][] = $bindable;
            }
        }
        $sql = implode('', $parts);
        return $sql;
    }

    /**
     * Internal helper to apply bindings using the correct PDO::PARAM_xxx type.
     *
     * @param PDOStatement The statement to apply the bindings to.
     * @return PDOStatement The PDOStatement ready for execution.
     */
    protected function applyBindings(PDOStatement $stmt) : PDOStatement
    {
        $bindings = $this->getBindings();
        $key = 1;
        foreach ($bindings as $value) {
            if (is_array($value)) {
                continue;
            }
            if (is_null($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_BOOL);
            } else {
                $stmt->bindValue($key, "$value", PDO::PARAM_STR);
            }
            ++$key;
        }
        return $stmt;
    }
}

