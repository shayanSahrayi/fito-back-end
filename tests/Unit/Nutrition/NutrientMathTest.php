<?php

namespace Tests\Unit\Nutrition;

use App\Domain\Nutrition\NutrientMath;
use PHPUnit\Framework\TestCase;

class NutrientMathTest extends TestCase
{
    public function test_per_100g_half_up_rounds_fifty_grams_up_and_forty_nine_down(): void
    {
        $this->assertSame(1, NutrientMath::scale(50, 1, 100));
        $this->assertSame(0, NutrientMath::scale(49, 1, 100));
        $this->assertSame(1, NutrientMath::line('per_100g', 50, 1, 0, 0, 0)['energy_kcal']);
        $this->assertSame(0, NutrientMath::line('per_100g', 49, 1, 0, 0, 0)['energy_kcal']);
    }

    public function test_per_unit_milli_uses_a_divisor_of_one_thousand(): void
    {
        $this->assertSame(300, NutrientMath::scale(1500, 200, 1000));
        $this->assertSame(300, NutrientMath::line('per_unit', 1500, 200, 0, 0, 0)['energy_kcal']);
    }
}
