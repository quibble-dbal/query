<?php

namespace Quibble\Tests;

use Quibble\Sqlite\Adapter;
use Quibble\Query\UpdateException;
use Quibble\Query\Buildable;
use PDO;

/**
 * Updating
 */
class UpdateTest
{
    public function __wakeup()
    {
        $this->pdo = new class(':memory:') extends Adapter {
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
    }

    /**
     * update should update a row {?}
     */
    public function testUpdate()
    {
        $res = $this->pdo->updateTable('test')
            ->where('id = ?', 1)
            ->execute(['foo' => 'douglas']);
        yield assert($res === true);
    }

    /**
     * update should return false if nothing was updated {?}
     */
    public function testNoUpdate()
    {
        $res = $this->pdo->updateTable('test')
            ->where('id = ?', 1234)
            ->execute(['name' => 'adams']);
        yield assert($res === false);
    }

    /**
     * update should throw an exception if nothing was updated {?}
     */
    public function testNoUpdateException()
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $e = null;
        try {
            $this->pdo->updateTable('test')
                ->where('id = ?', 12345)
                ->execute(['name' => 'adams']);
        } catch (UpdateException $e) {
        }
        yield assert($e instanceof UpdateException);
    }
}

