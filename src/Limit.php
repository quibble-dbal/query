<?php

namespace Quibble\Query;

trait Limit
{
    protected $limit = null;
    protected $offset = null;

    public function limit($limit, $offset = 0)
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }
}

