<?php

namespace App\Domain\Identity;

final class NationalCode
{
    public static function parse(string $input): string
    {
        $digits = strtr($input, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $digits = preg_replace('/\D+/', '', $digits) ?? '';

        if (preg_match('/^\d{10}$/', $digits) !== 1 || preg_match('/^(\d)\1{9}$/', $digits) === 1) {
            throw new IdentityException('invalid_national_code', 'کد ملی معتبر نیست.');
        }

        if ((int) $digits[9] !== self::checkDigit(substr($digits, 0, 9))) {
            throw new IdentityException('invalid_national_code', 'کد ملی معتبر نیست.');
        }

        return $digits;
    }

    public static function withCheckDigit(string $firstNine): string
    {
        if (preg_match('/^\d{9}$/', $firstNine) !== 1) {
            throw new IdentityException('invalid_national_code', 'کد ملی معتبر نیست.');
        }

        return $firstNine.self::checkDigit($firstNine);
    }

    private static function checkDigit(string $firstNine): int
    {
        $sum = 0;

        for ($index = 0; $index < 9; $index++) {
            $sum += (int) $firstNine[$index] * (10 - $index);
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? $remainder : 11 - $remainder;
    }
}
