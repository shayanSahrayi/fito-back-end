<?php

namespace App\Domain\Commerce;

use RuntimeException;

final class CommerceException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
