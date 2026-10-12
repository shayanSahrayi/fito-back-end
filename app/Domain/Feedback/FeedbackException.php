<?php

namespace App\Domain\Feedback;

use RuntimeException;

final class FeedbackException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
