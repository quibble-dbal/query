<?php

namespace Quibble\Tests;

use Quibble\Sqlite\Adapter;
use Quibble\Query\DeleteException;
use Quibble\Query\Buildable;
use PDO;

/**
 * Deletion
 */
class DeleteTest
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
     * delete should delete a row {?}
     */
    public function testDelete()
    {
        $res = $this->pdo->deleteFrom('test')
            ->where(['id' => 1])
            ->execute();
        yield assert($res === true);
    }
    
    /**
     * delete should return false if nothing was deleted {?}
     */
    public function testNoDelete(Adapter &$db = null)
    {
        $res = $this->pdo->deleteFrom('test')
            ->where(['id' => 12345])
            ->execute();
        yield assert($res === false);
    }
    
    /**
     * delete should throw an exception if nothing was deleted {?}
     */
    public function testNoDeleteWithException(Adapter &$db = null)
    {
        $e = null;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $this->pdo->deleteFrom('test')
                ->where(['id' => 12345])
                ->execute();
        } catch (DeleteException $e) {
        }
        yield assert($e instanceof DeleteException);
    }
}

