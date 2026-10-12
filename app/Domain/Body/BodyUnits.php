<?php

namespace App\Domain\Body;

final class BodyUnits
{
    /**
     * @var array<string, array{code: string, scale: int, min: int}>
     */
    public const FIELDS = [
        'weight_kg' => ['code' => 'weight_g', 'scale' => 3, 'min' => 1],
        'muscle_mass_kg' => ['code' => 'muscle_mass_g', 'scale' => 3, 'min' => 1],
        'height_cm' => ['code' => 'height_mm', 'scale' => 1, 'min' => 1],
        'neck_cm' => ['code' => 'neck_mm', 'scale' => 1, 'min' => 1],
        'chest_cm' => ['code' => 'chest_mm', 'scale' => 1, 'min' => 1],
        'waist_cm' => ['code' => 'waist_mm', 'scale' => 1, 'min' => 1],
        'hip_cm' => ['code' => 'hip_mm', 'scale' => 1, 'min' => 1],
        'left_arm_cm' => ['code' => 'left_arm_mm', 'scale' => 1, 'min' => 1],
        'right_arm_cm' => ['code' => 'right_arm_mm', 'scale' => 1, 'min' => 1],
        'left_thigh_cm' => ['code' => 'left_thigh_mm', 'scale' => 1, 'min' => 1],
        'right_thigh_cm' => ['code' => 'right_thigh_mm', 'scale' => 1, 'min' => 1],
        'body_fat_percent' => ['code' => 'body_fat_bp', 'scale' => 2, 'min' => 0],
        'body_water_percent' => ['code' => 'body_water_bp', 'scale' => 2, 'min' => 0],
        'visceral_fat_score' => ['code' => 'visceral_fat_score', 'scale' => 0, 'min' => 0],
        'bmi' => ['code' => 'bmi_centi', 'scale' => 2, 'min' => 1],
    ];

    public static function toStorage(mixed $value, int $scale, int $min): int
    {
        $stored = self::minor($value, $scale);

        if ($stored < $min || $stored > 4294967295) {
            throw new BodyException('invalid_metric', 'مقدار معیار معتبر نیست.');
        }

        return $stored;
    }

    public static function fromStorage(int $stored, int $scale): int|string
    {
        $negative = $stored < 0;
        $absolute = abs($stored);

        if ($scale === 0) {
            return $negative ? -$absolute : $absolute;
        }

        $factor = 10 ** $scale;
        $whole = intdiv($absolute, $factor);
        $fraction = $absolute % $factor;

        if ($fraction === 0) {
            return $negative ? -$whole : $whole;
        }

        $digits = rtrim(str_pad((string) $fraction, $scale, '0', STR_PAD_LEFT), '0');
        $text = $whole.'.'.$digits;

        return $negative ? '-'.$text : $text;
    }

    public static function scaleOf(string $code): int
    {
        foreach (self::FIELDS as $field) {
            if ($field['code'] === $code) {
                return $field['scale'];
            }
        }

        return 0;
    }

    public static function fieldFor(string $code): ?string
    {
        foreach (self::FIELDS as $name => $field) {
            if ($field['code'] === $code && $name !== 'bmi') {
                return $name;
            }
        }

        return $code === 'bmi_centi' ? 'bmi' : null;
    }

    private static function minor(mixed $value, int $scale): int
    {
        if (is_int($value)) {
            return self::scaleInt($value, $scale);
        }

        if (is_string($value) && preg_match('/^\d+(\.\d+)?$/', $value) === 1) {
            return self::fromDecimal($value, $scale);
        }

        if (is_float($value)) {
            return self::fromDecimal(number_format($value, $scale, '.', ''), $scale);
        }

        throw new BodyException('invalid_metric', 'مقدار معیار معتبر نیست.');
    }

    private static function scaleInt(int $value, int $scale): int
    {
        $factor = 10 ** $scale;

        if ($value > intdiv(PHP_INT_MAX, $factor)) {
            throw new BodyException('invalid_metric', 'مقدار معیار معتبر نیست.');
        }

        return $value * $factor;
    }

    private static function fromDecimal(string $text, int $scale): int
    {
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');

        if ($scale === 0 && $fraction !== '' && (int) $fraction !== 0) {
            throw new BodyException('invalid_metric', 'این معیار فقط عدد صحیح می‌گیرد.');
        }

        if (strlen($fraction) > $scale) {
            throw new BodyException('invalid_metric', 'رقم اعشار این معیار بیش از حد است.');
        }

        $fraction = str_pad(substr($fraction, 0, $scale), $scale, '0');
        $factor = 10 ** $scale;

        if ((int) $whole > intdiv(PHP_INT_MAX - (int) $fraction, $factor)) {
            throw new BodyException('invalid_metric', 'مقدار معیار معتبر نیست.');
        }

        return (int) $whole * $factor + (int) $fraction;
    }
}
