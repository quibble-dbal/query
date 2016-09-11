<?php

/**
 * @package Quibble\Query
 */

namespace Quibble\Query;

use Quibble\Dabble\Exception;

class DeleteException extends Exception
{
    public function __construct($message = '', $code = null, $previous = null)
    {
        parent::__construct($message, self::NOAFFECTEDROWS, $previous);
    }
}

