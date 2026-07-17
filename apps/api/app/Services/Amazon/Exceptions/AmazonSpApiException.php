<?php

namespace App\Services\Amazon\Exceptions;

use RuntimeException;

final class AmazonSpApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $requestId = null,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, $status);
    }
}
