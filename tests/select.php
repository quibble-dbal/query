<?php

use Quibble\Query\Select;
use Quibble\Sqlite\Adapter;

/**
 * Selecting data.
 */
return function ($test) : Generator {
    $test->beforeEach(function () use (&$pdo) {
        $pdo = new Adapter(':memory:');
        $pdo->exec(<<<EOT
DROP TABLE IF EXISTS test;
CREATE TABLE test (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    foo VARCHAR(255) NOT NULL
);
INSERT INTO test (foo) VALUES ('bar'), ('baz'), ('buzz');

EOT
        );
    });

    /**
     * We can correctly instantiate a Builder. We can then chain it using
     * `where`. We can also add an `orWhere` accepting multiple parameters.
     */
    yield function () use ($pdo) {
        $query = new Select($pdo, 'test');
        yield assert($query instanceof Select);
        $query->where('id = ?', 1);
        yield assert($query instanceof Select);
        $query->orWhere('id = ? AND foo = ?', 2, 'baz');
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        yield assert(count($result) == 2);
    };
};

