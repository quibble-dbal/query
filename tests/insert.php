<?php

use Quibble\Sqlite\Adapter;
use Quibble\Dabble\SqlException;
use Quibble\Query\InsertException;
use Quibble\Query\Buildable;

/**
 * Insertions
 */
return function ($test) : Generator {
    $test->beforeEach(function () use (&$pdo) {
        $pdo = new class(':memory:') extends Adapter {
            use Buildable;
        };
        $pdo->exec(<<<EOT
DROP TABLE IF EXISTS test;
CREATE TABLE test (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    foo VARCHAR(255) NOT NULL
);

EOT
        );
    });

    /** insert should insert a new row */
    yield function () use ($pdo) {
        $res = $pdo->insertInto('test')
            ->execute(['foo' => 'monomelodies']);
        assert($res === true);
    };

    /** insert should return false if nothing was inserted */
    yield function () use ($pdo) {
        $res = $pdo->insertInto('test2')
            ->execute(['foo' => null]);
        assert($res === false);
    };

    /** insert should throw an exception if nothing was inserted */
    yield function () use ($pdo) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $e = null;
        try {
            $pdo->insertInto('test2')
                ->execute(['foo' => null]);
        } catch (SqlException $e) {
        }
        assert($e instanceof SqlException);
    };
};

