<?php

namespace Quibble\Query;

use PDOException;

abstract class Exception extends PDOException
{
    const PREPARATION = 1;
    const EXECUTION = 2;
    const EMPTYRESULT = 3;
    const NOAFFECTEDROWS = 4;
}

