<?php

use Quibble\Sqlite\Adapter;
use Quibble\Query\DeleteException;
use Quibble\Query\Buildable;

/**
 * Deletion
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
    foo VARCHAR(255) NOT NULL
);
INSERT INTO test (foo) VALUES ('bar'), ('baz'), ('buzz');

EOT
        );
    });

    /** delete should delete a row */
    yield function () use (&$pdo) {
        $res = $pdo->delete('test')
            ->where('id = ?', 1)
            ->execute();
        assert($res === true);
    };
    
    /** delete should return false if nothing was deleted */
    yield function () use (&$pdo) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $res = $pdo->delete('test')
            ->where('id = ?', 12345)
            ->execute();
        assert($res === false);
    };
    
    /** delete should throw an exception if nothing was deleted */
    yield function () use (&$pdo) {
        $e = null;
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $pdo->delete('test')
                ->where('id = ?', 12345)
                ->execute();
        } catch (DeleteException $e) {
        }
        assert($e instanceof DeleteException);
    };
};

