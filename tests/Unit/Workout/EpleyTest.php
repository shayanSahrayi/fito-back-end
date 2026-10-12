<?php

namespace Tests\Unit\Workout;

use App\Domain\Workout\Epley;
use PHPUnit\Framework\TestCase;

class EpleyTest extends TestCase
{
    public function test_five_reps_at_one_hundred_kilograms_rounds_half_up(): void
    {
        $this->assertSame(116667, Epley::estimated1rm(100000, 5));
    }

    public function test_ten_reps_lands_on_an_exact_value(): void
    {
        $this->assertSame(40000, Epley::estimated1rm(30000, 10));
    }
}
