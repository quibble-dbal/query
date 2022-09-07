<?php

/**
 * @package Quibble\Query
 */

namespace Quibble\Query;

use Quibble\Dabble\Exception;
use Throwable;

/**
 * Exception thrown when a SELECT statement returns no results.
 */
class SelectException extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param Throwable $previous
     */
    public function __construct(string $message = '', int $code = null, Throwable $previous = null)
    {
        parent::__construct($message, self::EMPTYRESULT, $previous);
    }
}

