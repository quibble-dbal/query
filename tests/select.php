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

DROP TABLE IF EXISTS test2;
CREATE TABLE test2 (
    id INTEGER NOT NULL,
    bar VARCHAR(255)
);
INSERT INTO test2 (id, bar) VALUES (1, 'baz');

EOT
        );
    });

    /**
     * We can correctly instantiate a Builder. We can then chain it using
     * `where`. We can also add an `orWhere` accepting multiple parameters.
     */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        assert($query instanceof Select);
        $query->where('id = ?', 1);
        assert($query instanceof Select);
        $query->orWhere('id = ? AND foo = ?', 2, 'baz');
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        assert(count($result) == 2);
    };

    /**
     * We can join with USING using a simple join parameter.
     */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $query->join('test2', 'id')
            ->where('id = ?', 1);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        assert($result['bar'] == 'baz');
    };

    /**
     * We can join explicitly using a complex join parameter.
     */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $query->join('test2', 'test.id = test2.id')
            ->where('test.id = ?', 1);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        assert($result['bar'] == 'baz');
    };
};

