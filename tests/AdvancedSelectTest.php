<?php

namespace Quibble\Tests;

use Quibble\Query\Select;
use Quibble\Sqlite\Adapter;
use PDO;
use Generator;

/**
 * Adavanced selecting of data.
 */
class AdvancedSelectTest
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

DROP TABLE IF EXISTS test2;
CREATE TABLE test2 (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bar VARCHAR(255) NOT NULL
);
INSERT INTO test2 (bar) VALUES ('bar'), ('baz'), ('buzz');

EOT
        );
    }

    /**
     * We can use a builder to union tables distinctly {?} or with the ALL
     * SQL keywords.
     */
    public function unions()
    {
        $query = new Select($this->pdo, 'test');
        $query->union(new Select($this->pdo, 'test2'));
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        yield assert(count($result) == 3);

        $query = new Select($this->pdo, 'test');
        $query->unionAll(new Select($this->pdo, 'test2'));
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        yield assert(count($result) == 6);
    }

    /**
     * We can also use the builder to join two tables {?}.
     */
    public function joins()
    {
        $query = new Select($this->pdo, 'test');
        $result = $query->join('test2 USING (id)')
            ->fetch(PDO::FETCH_ASSOC);
        yield assert(count($result) == 3);
    }

    /**
     * Fetching using `generator` gives us a generator {?}.
     */
    public function generator()
    {
        $query = new Select($this->pdo, 'test');
        $result = $query->generate(PDO::FETCH_ASSOC);
        yield assert($result instanceOf Generator);
    }

    /**
     * Fetching using `count` gives us the count {?}.
     */
    public function counting()
    {
        $query = new Select($this->pdo, 'test');
        $result = $query->count();
        yield assert($result == 3);
    }

    /**
     * The `in` method correctly makes an IN statement {?}.
     */
    public function in()
    {
        $query = new Select($this->pdo, 'test');
        $result = $query->where($query->in('id', [1, 2]))->fetchAll();
        yield assert(count($result) == 2);
    }

    /**
     * If a bindable is itself a Select query it becomes a subquery {?}.
     */
    public function subQuery()
    {
        $query = new Select($this->pdo, 'test');
        $subquery = new Select($this->pdo, 'test2');
        $subquery->where('bar = ?', 'bar')
            ->orWhere('bar = ?', 'baz')
            ->select('bar');
        $query->where('foo IN (?)', $subquery);
        $result = $query->fetchAll();
        yield assert(count($result) == 2);
    }

    /**
     * If the SQL part of a WHERE call is itself callable, it becomes a grouping
     * function/object {?}.
     */
    public function groupedWhere()
    {
        $query = new Select($this->pdo, 'test');
        $result = $query->where('id > ?', 1)
            ->andWhere(function ($group) {
                $group->where('foo', 'bar')
                    ->orWhere('foo', 'baz');
            })
            ->fetchAll();
        yield assert(count($result) == 1);
    }
}

