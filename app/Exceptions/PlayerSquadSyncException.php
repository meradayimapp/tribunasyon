<?php

namespace App\Exceptions;

use RuntimeException;

class PlayerSquadSyncException extends RuntimeException
{
    // Only safe, administrator-facing messages belong in this exception.
}
