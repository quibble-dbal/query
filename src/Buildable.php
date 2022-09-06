<?php

namespace Quibble\Query;

trait Buildable
{
    public function select(string|Select $table) : Select
    {
        return new Select($this, $table);
    }

    public function insert(string $table) : Insert
    {
        return new Insert($this, $table);
    }

    public function update(string $table) : Update
    {
        return new Update($this, $table);
    }

    public function delete(string $table) : Delete
    {
        return new Delete($this, $table);
    }
}

