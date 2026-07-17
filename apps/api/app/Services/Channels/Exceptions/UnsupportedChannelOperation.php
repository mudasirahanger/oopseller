<?php

namespace App\Services\Channels\Exceptions;

use App\Enums\Platform;
use RuntimeException;

class UnsupportedChannelOperation extends RuntimeException
{
    public static function for(Platform $platform, string $operation): self
    {
        return new self("The {$platform->label()} integration does not support {$operation} yet.");
    }
}
