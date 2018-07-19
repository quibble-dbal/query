<?php

use Quibble\Sqlite\Adapter;
use Quibble\Query\UpdateException;
use Quibble\Query\Buildable;

/**
 * Updating
 */
return function ($test) : Generator {
    $test->beforeEach(function () use (&$pdo) {
        $pdo = new class(':memory:') extends Adapter {
            use Buildable;
        };
        $this->pdo->exec(<<<EOT
DROP TABLE IF EXISTS test;
CREATE TABLE test (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    foo VARCHAR(255) NOT NULL
);
INSERT INTO test (foo) VALUES ('bar'), ('baz'), ('buzz');

EOT
        );
    });

    /** update should update a row */
    yield function () use ($pdo) {
        $res = $pdo->updateTable('test')
            ->where('id = ?', 1)
            ->execute(['foo' => 'douglas']);
        assert($res === true);
    };

    /** update should return false if nothing was updated */
    yield function () use ($pdo) {
        $res = $pdo->updateTable('test')
            ->where('id = ?', 1234)
            ->execute(['foo' => 'adams']);
        assert($res === false);
    };

    /** update should throw an exception if nothing was updated */
    yield function () use ($pdo) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $e = null;
        try {
            $this->pdo->updateTable('test')
                ->where('id = ?', 12345)
                ->execute(['foo' => 'adams']);
        } catch (UpdateException $e) {
        }
        assert($e instanceof UpdateException);
    };
};

