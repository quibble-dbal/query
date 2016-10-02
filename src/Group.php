<?php

namespace Quibble\Query;

use DomainException;

class Group extends Builder
{
    use Where;
    use Bindable;

    public function __toString() : string
    {
        return implode(' ', $this->wheres);
    }

    public function getStatement(array $driver_options = [])
    {
        throw new DomainException("Cannot get a statement for a group.");
    }

    public function getExecutedStatement(array $driver_options = [])
    {
        throw new DomainException("Cannot get a statement for a group.");
    }
}

