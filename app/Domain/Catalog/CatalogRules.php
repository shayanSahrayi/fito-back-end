<?php

namespace App\Domain\Catalog;

final class CatalogRules
{
    public const TRACKING_TYPES = ['weight_reps', 'reps', 'duration', 'distance'];

    public const INVOLVEMENTS = ['primary', 'secondary'];

    public const MEDIA_TYPES = ['image', 'video'];

    public static function tracking(mixed $value): string
    {
        $type = (string) $value;

        if (! in_array($type, self::TRACKING_TYPES, true)) {
            throw new CatalogException('invalid_tracking_type', 'نوع ثبت حرکت معتبر نیست.');
        }

        return $type;
    }

    public static function requiredText(mixed $value, string $code, string $message): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            throw new CatalogException($code, $message);
        }

        return $text;
    }

    public static function boundedText(mixed $value, int $max, string $code, string $message): string
    {
        $text = self::requiredText($value, $code, $message);

        if (mb_strlen($text) > $max) {
            throw new CatalogException($code, $message);
        }

        return $text;
    }

    public static function optionalText(mixed $value, ?int $max = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if ($max !== null && mb_strlen($text) > $max) {
            throw new CatalogException('invalid_text', 'متن معتبر نیست.');
        }

        return $text;
    }
}
