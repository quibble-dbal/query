<?php

use Quibble\Query\{ Select, Join };
use Quibble\Sqlite\Adapter;

/**
 * Selecting data.
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
    id INTEGER NOT NULL,
    bar VARCHAR(255)
);
INSERT INTO test2 (id, bar) VALUES (1, 'baz');
INSERT INTO test2 (id, bar) VALUES (4, 'buz');

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
        $query = $query->where('id = ?', 1);
        assert($query instanceof Select);
        $query = $query->orWhere('id = ? AND foo = ?', 2, 'baz');
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        assert(count($result) == 2);
    };

    /**
     * We can join with USING using a simple join parameter.
     */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $query = $query->join(function (Join $join) : Join {
            return $join->inner('test2')->using('id');
        })->where('id = ?', 1);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        assert($result['bar'] == 'baz');
    };

    /**
     * We can left join with USING using a simple join parameter.
     */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $query = $query->join(function (Join $join) : Join {
            return $join->left('test2')->using('id');
        })->where('id = ?', 2);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        assert($result['bar'] == null);
    };

    /**
     * We can right join with USING using a simple join parameter.
     */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $query = $query->join(function (Join $join) : Join {
            return $join->right('test2')->using('id');
        })->where('id = ?', 4);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        assert($result['foo'] == null);
    };

    /**
     * We can full join with USING using a simple join parameter.
     */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $query = $query->join(function (Join $join) : Join {
            return $join->full('test2')->using('id');
        });
        $result = count($query->fetchAll(PDO::FETCH_ASSOC));
        assert($result == 4);
    };

    /**
     * We can join explicitly using a complex join parameter.
     */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $query = $query->join(function (Join $join) : Join {
            return $join->inner('test2')->on('test.id = test2.id');
        })->where('test.id = ?', 1);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        assert($result['bar'] == 'baz');
    };

    /**
     * We can join with bindings.
     */
    yield function () use (&$pdo) {
        $query = new Select($pdo, 'test');
        $query = $query->join(function (Join $join) : Join {
            return $join->inner('test2')->on('test2.id = ?', 1);
        })->where('test.id = ?', 1);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        assert($result['bar'] == 'baz');
    };
};

