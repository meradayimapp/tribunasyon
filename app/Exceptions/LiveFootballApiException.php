<?php

namespace App\Exceptions;

use RuntimeException;

class LiveFootballApiException extends RuntimeException
{
    // The message intentionally never contains a request URL or API key.
    public function __construct(
        string $message,
        public readonly string $category = 'provider',
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}
