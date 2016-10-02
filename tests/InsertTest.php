<?php

namespace Quibble\Tests;

use Quibble\Sqlite\Adapter;
use Quibble\Dabble\SqlException;
use Quibble\Query\InsertException;
use Quibble\Query\Buildable;
use PDO;

/**
 * Insertions
 */
class InsertTest
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

EOT
        );
    }

    /**
     * insert should insert a new row {?}
     */
    public function testInsert()
    {
        $res = $this->pdo->insertInto('test')
            ->execute(['foo' => 'monomelodies']);
        yield assert($res === true);
    }

    /**
     * insert should return false if nothing was inserted {?}
     */
    public function testNoInsert()
    {
        $res = $this->pdo->insertInto('test2')
            ->execute(['foo' => null]);
        yield assert($res === false);
    }

    /**
     * insert should throw an exception if nothing was inserted {?}
     */
    public function testNoInsertWithException()
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $e = null;
        try {
            $this->pdo->insertInto('test2')
                ->execute(['foo' => null]);
        } catch (SqlException $e) {
        }
        yield assert($e instanceof SqlException);
    }
}

