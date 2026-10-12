<?php

namespace App\Domain\Commerce;

final class TestGateway
{
    public static function enabled(string $environment): bool
    {
        return in_array($environment, ['local', 'testing'], true);
    }
}
