<?php

namespace Quibble\Query;

const ERRMODE_EXCEPTION = 1;
const ERRMODE_DEFAULT = 2;

trait Buildable
{
    private static $errMode;

    public static function from($table)
    {
        return new Builder($this, $table);
    }

    public static function setErrorMode($mode)
    {
        self::$errMode = $mode;
    }

    public static function getErrorMode()
    {
        if (!isset(self::$errMode)) {
            self::$errMode = self::ERRMODE_DEFAULT;
        }
        return self::$errMode;
    }
}

