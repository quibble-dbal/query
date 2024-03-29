<?php

use Quibble\Sqlite\Adapter;
use Quibble\Dabble\SqlException;
use Quibble\Query\InsertException;
use Quibble\Query\Buildable;

/**
 * Insertions
 */
return function () : Generator {
    $this->beforeEach(function () use (&$pdo) {
        $pdo = new class(':memory:') extends Adapter {
            use Buildable;
        };
        $pdo->exec(<<<EOT
DROP TABLE IF EXISTS test;
CREATE TABLE test (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    foo VARCHAR(255) NOT NULL,
    bar TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

EOT
        );
    });

    /** insert should insert a new row */
    yield function () use (&$pdo) {
        $res = $pdo->insert('test')
            ->execute(['foo' => 'monomelodies']);
        assert($res === true);
    };

    /** We can insert raw values by wrapping in an array */
    yield function () use (&$pdo) {
        $res = $pdo->insert('test')
            ->execute(['foo' => 'monomelodies', 'bar' => [$pdo->now()]]);
        assert($res === true);
    };

    /** insert should return false if nothing was inserted */
    yield function () use (&$pdo) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $res = $pdo->insert('test2')
            ->execute(['foo' => null]);
        assert($res === false);
    };

    /** insert should throw an exception if nothing was inserted */
    yield function () use (&$pdo) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $e = null;
        try {
            $pdo->insert('test2')
                ->execute(['foo' => null]);
        } catch (SqlException $e) {
        }
        assert($e instanceof SqlException);
    };
};

