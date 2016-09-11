<?php

namespace Quibble\Query;

trait Buildable
{
    private static $errMode;

    public static function selectFrom($table) : Select
    {
        return new Select($this, $table);
    }

    public static function insertInto($table) : Insert
    {
        return new Insert($this, $table);
    }

    public static function updateTable($table) : Update
    {
        return new Update($this, $table);
    }

    public static function deleteFrom($table) : Delete
    {
        return new Delete($this, $table);
    }
}

