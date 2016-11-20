<?php

namespace Quibble\Query;

use PDO;
use PDOStatement;
use Quibble\Dabble\Raw;

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
     * replaces any binding where the value is an instance of Quibble\Dabble\Raw
     * with its raw, `__toString()`'d value.
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
            if ($bindable instanceof Raw) {
                $parts[$i] .= "$bindable";
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
        foreach ($bindings as $key => $value) {
            $pdokey = $key + 1;
            if (is_null($value)) {
                $stmt->bindValue($pdokey, $value, PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $stmt->bindValue($pdokey, $value, PDO::PARAM_BOOL);
            } else {
                $stmt->bindValue($pdokey, $value, PDO::PARAM_STR);
            }
        }
        return $stmt;
    }
}

