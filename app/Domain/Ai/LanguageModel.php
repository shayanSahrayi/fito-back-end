<?php

namespace App\Domain\Ai;

interface LanguageModel
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function reply(string $intent, array $context): string;

    public function name(): string;
}
