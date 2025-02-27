<?php

/**
 * @package Quibble\Query
 */

namespace Quibble\Query;

use Quibble\Dabble\Exception;
use Throwable;

/**
 * Thrown when an INSERT statement didn't actually insert anything.
 */
class InsertException extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param Throwable $previous
     */
    public function __construct(string $message = '', ?int $code = null, ?Throwable $previous = null)
    {
        parent::__construct($message, self::NOAFFECTEDROWS, $previous);
    }
}

