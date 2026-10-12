<?php

namespace App\Domain\Workout;

final class Epley
{
    public static function estimated1rm(int $weightGrams, int $reps): int
    {
        return intdiv($weightGrams * (30 + $reps) + 15, 30);
    }
}
