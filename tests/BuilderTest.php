<?php

namespace Quibble\Tests;

use Quibble\Query\Builder;
use Quibble\Sqlite\Adapter;
use PDO;

class BuilderTest
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
     * `where` {?} after which we have a new object {?}. We can also add an
     * `orWhere` accepting multiple parameters {?}.
     */
    public function instantiation()
    {
        $query = new Builder($this->pdo, 'test');
        yield assert($query instanceof Builder);
        $query2 = $query->where('id = ?', 1);
        yield assert($query2 instanceof Builder);
        yield assert($query !== $query2);
        unset($query);
        $query = $query2->orWhere('id = ? AND foo = ?', 2, 'baz');
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        yield assert(count($result) == 2);
    }

    /**
     * We can correctly instantiate a Builder and query using `in` {?}.
     * The correct results are yielded {?}.
     */
    public function in()
    {
        $query = (new Builder($this->pdo, 'test'))
            ->in('foo', ['bar', 'baz']);
        yield assert($query instanceof Builder);
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        yield assert(count($result) == 2);
    }
}

