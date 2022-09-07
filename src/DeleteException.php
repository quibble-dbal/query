<?php

/**
 * @package Quibble\Query
 */

namespace Quibble\Query;

use Quibble\Dabble\Exception;
use Throwable;

/**
 * Exception thrown if a delete query did not actually remove anything (thrown
 * when ERRMODE is set to EXCEPTION).
 */
class DeleteException extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param Throwable $previous
     */
    public function __construct(string $message = '', int $code = null, Throwable $previous = null)
    {
        parent::__construct($message, self::NOAFFECTEDROWS, $previous);
    }
}

