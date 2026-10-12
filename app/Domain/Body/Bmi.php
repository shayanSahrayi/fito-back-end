<?php

namespace App\Domain\Body;

final class Bmi
{
    public static function centi(int $weightG, int $heightMm): int
    {
        if ($weightG < 1 || $heightMm < 1) {
            throw new BodyException('invalid_metric', 'وزن یا قد برای BMI کافی نیست.');
        }

        $divisor = $heightMm * $heightMm;
        $half = intdiv($divisor, 2);

        if ($weightG > intdiv(PHP_INT_MAX - $half, 100000)) {
            throw new BodyException('invalid_metric', 'مقدار برای محاسبه بزرگ است.');
        }

        $scaled = intdiv($weightG * 100000 + $half, $divisor);

        if ($scaled > 4294967295) {
            throw new BodyException('invalid_metric', 'مقدار برای محاسبه بزرگ است.');
        }

        return $scaled;
    }
}
