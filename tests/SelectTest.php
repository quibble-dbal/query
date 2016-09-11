<?php

namespace Quibble\Tests;

use Quibble\Query\Select;
use Quibble\Sqlite\Adapter;
use PDO;

/**
 * Selecting data.
 */
class SelectTest
{
    public function __wakeup()
    {
        $this->pdo = new Adapter(':memory:');
        $this->pdo->exec(<<<EOT
DROP TABLE IF EXISTS test;
CREATE TABLE test (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    foo VARCHAR(255) NOT NULL
);
INSERT INTO test (foo) VALUES ('bar'), ('baz'), ('buzz');

EOT
        );
    }

    /**
     * We can correctly instantiate a Builder {?}. We can then chain it using
     * `where` {?}. We can also add an `orWhere` accepting multiple parameters
     * {?}.
     */
    public function instantiation()
    {
        $query = new Select($this->pdo, 'test');
        yield assert($query instanceof Select);
        $query->where('id = ?', 1);
        yield assert($query instanceof Select);
        $query->orWhere('id = ? AND foo = ?', 2, 'baz');
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        yield assert(count($result) == 2);
    }

    /**
     * We can correctly instantiate a Builder and query using `in` {?}.
     * The correct results are yielded {?}.
     */
    public function in()
    {
        $query = (new Select($this->pdo, 'test'))
            ->in('foo', ['bar', 'baz']);
        yield assert($query instanceof Select);
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        yield assert(count($result) == 2);
    }
}

