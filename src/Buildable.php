<?php

namespace Quibble\Query;

trait Buildable
{
    public function selectFrom($table) : Select
    {
        return new Select($this, $table);
    }

    public function insertInto($table) : Insert
    {
        return new Insert($this, $table);
    }

    public function updateTable($table) : Update
    {
        return new Update($this, $table);
    }

    public function deleteFrom($table) : Delete
    {
        return new Delete($this, $table);
    }
}

