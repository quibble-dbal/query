<?php

namespace Quibble\Query;

trait Limit
{
    protected $limit = null;
    protected $offset = null;

    /**
     * @param int $limit
     * @param int $offset Defaults to 0
     * @return Quibble\Query\Builder
     */
    public function limit(int $limit, int $offset = 0) : Builder
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }
}

