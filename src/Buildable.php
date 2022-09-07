<?php

namespace Quibble\Query;

/**
 * Adapter using this trait get the four convenience methods `select`, `insert`,
 * `update` and `delete` to kickstart a query builder.
 */
trait Buildable
{
    /**
     * @param string|Quibble\Query\Select $table
     * @return Quibble\Query\Select
     */
    public function select(string|Select $table) : Select
    {
        return new Select($this, $table);
    }

    /**
     * @param string table
     * @return Quibble\Query\Insert
     */
    public function insert(string $table) : Insert
    {
        return new Insert($this, $table);
    }

    /**
     * @param string table
     * @return Quibble\Query\Update
     */
    public function update(string $table) : Update
    {
        return new Update($this, $table);
    }

    /**
     * @param string table
     * @return Quibble\Query\Delete
     */
    public function delete(string $table) : Delete
    {
        return new Delete($this, $table);
    }
}

