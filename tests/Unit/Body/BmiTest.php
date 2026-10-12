<?php

namespace Tests\Unit\Body;

use App\Domain\Body\Bmi;
use App\Domain\Body\BodyUnits;
use PHPUnit\Framework\TestCase;

class BmiTest extends TestCase
{
    public function test_seventy_kilograms_at_one_hundred_seventy_five_centimetres_is_2286(): void
    {
        $this->assertSame(2286, Bmi::centi(70000, 1750));
    }

    public function test_kilograms_and_centimetres_round_trip(): void
    {
        $this->assertSame(70500, BodyUnits::toStorage(70.5, 3, 1));
        $this->assertSame('70.5', BodyUnits::fromStorage(70500, 3));
        $this->assertSame(1755, BodyUnits::toStorage('175.5', 1, 1));
        $this->assertSame('175.5', BodyUnits::fromStorage(1755, 1));
        $this->assertSame(1525, BodyUnits::toStorage('15.25', 2, 0));
        $this->assertSame('15.25', BodyUnits::fromStorage(1525, 2));
    }
}
