<?php

namespace Quibble\Query;

trait Buildable
{
    public function select($table) : Select
    {
        return new Select($this, $table);
    }

    public function insert($table) : Insert
    {
        return new Insert($this, $table);
    }

    public function update($table) : Update
    {
        return new Update($this, $table);
    }

    public function delete($table) : Delete
    {
        return new Delete($this, $table);
    }
}

