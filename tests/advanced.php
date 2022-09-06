<?php

use Quibble\Query\Select;
use Quibble\Sqlite\Adapter;

/**
 * Adavanced selecting of data.
 */
return function () : Generator {
    $this->beforeEach(function () use (&$pdo) {
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
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bar VARCHAR(255) NOT NULL
);
INSERT INTO test2 (bar) VALUES ('bar'), ('baz'), ('buzz');

EOT
        );
    });

    /** We can use a builder to union tables distinctly or with the ALL SQL keyword. */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $query->union(new Select($pdo, 'test2'));
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        yield assert(count($result) == 3);

        $query = new Select($pdo, 'test');
        $query->unionAll(new Select($pdo, 'test2'));
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        yield assert(count($result) == 6);
    };

    /** We can also use the builder to join two tables. */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $result = $query->join('test2 USING (id)')
            ->fetch(PDO::FETCH_ASSOC);
        yield assert(count($result) == 3);
    };

    /** Fetching using `generator` gives us a generator. */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $result = $query->generate(PDO::FETCH_ASSOC);
        yield assert($result instanceOf Generator);
    };

    /** Fetching using `count` gives us the count. */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $result = $query->count();
        yield assert($result == 3);
    };

    /** The `in` method correctly makes an IN statement. */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $result = $query->where($query->in('id', [1, 2]))->fetchAll();
        yield assert(count($result) == 2);
    };

    /** If a bindable is itself a Select query it becomes a subquery. */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $subquery = new Select($pdo, 'test2');
        $subquery->where('bar = ?', 'bar')
            ->orWhere('bar = ?', 'baz')
            ->select('bar');
        $query->where('foo IN (?)', $subquery);
        $result = $query->fetchAll();
        yield assert(count($result) == 2);
    };

    /**
     * If the SQL part of a WHERE call is itself callable, it becomes a grouping
     * function/object.
     */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $result = $query->where('id > ?', 1)
            ->andWhere(function ($group) {
                $group->where('foo', 'bar')
                    ->orWhere('foo', 'baz');
            })
            ->fetchAll();
        yield assert(count($result) == 1);
    };
};

