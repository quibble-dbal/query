<?php

namespace Quibble\Query;

/**
 * Trait supplying the LIMIT functionality, to be used where appropriate.
 */
trait Limit
{
    protected $limit = null;
    protected $offset = null;

    /**
     * @param int $limit
     * @param int $offset Defaults to 0
     * @return self
     */
    public function limit(int $limit, int $offset = 0) : self
    {
        $that = clone $this;
        $that->limit = $limit;
        $that->offset = $offset;
        return $that;
    }
}

