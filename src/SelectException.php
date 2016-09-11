<?php

/**
 * @package Quibble\Query
 */

namespace Quibble\Query;

class SelectException extends Exception
{
    public function __construct($message = '', $code = null, $previous = null)
    {
        parent::__construct($message, self::EMPTYRESULT, $previous);
    }
}

