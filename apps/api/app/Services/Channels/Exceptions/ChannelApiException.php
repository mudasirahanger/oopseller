<?php

namespace App\Services\Channels\Exceptions;

use App\Enums\Platform;
use RuntimeException;

class ChannelApiException extends RuntimeException
{
    public function __construct(
        public readonly Platform $platform,
        string $message,
        public readonly int $status = 0,
    ) {
        parent::__construct($message);
    }
}
