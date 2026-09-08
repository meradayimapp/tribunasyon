<?php

namespace App\Exceptions;

use RuntimeException;

class LiveFootballApiException extends RuntimeException
{
    // The message intentionally never contains a request URL or API key.
}
