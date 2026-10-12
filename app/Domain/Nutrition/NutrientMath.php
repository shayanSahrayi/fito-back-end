<?php

namespace App\Domain\Nutrition;

final class NutrientMath
{
    /**
     * @return array{energy_kcal: int, protein_mg: int, carbohydrate_mg: int, fat_mg: int}
     */
    public static function line(
        string $basis,
        int $quantity,
        int $energyKcal,
        int $proteinMg,
        int $carbohydrateMg,
        int $fatMg,
    ): array {
        $divisor = $basis === 'per_unit' ? 1000 : 100;

        return [
            'energy_kcal' => self::scale($quantity, $energyKcal, $divisor),
            'protein_mg' => self::scale($quantity, $proteinMg, $divisor),
            'carbohydrate_mg' => self::scale($quantity, $carbohydrateMg, $divisor),
            'fat_mg' => self::scale($quantity, $fatMg, $divisor),
        ];
    }

    /**
     * @param  array{energy_kcal: int, protein_mg: int, carbohydrate_mg: int, fat_mg: int}  $left
     * @param  array{energy_kcal: int, protein_mg: int, carbohydrate_mg: int, fat_mg: int}  $right
     * @return array{energy_kcal: int, protein_mg: int, carbohydrate_mg: int, fat_mg: int}
     */
    public static function add(array $left, array $right): array
    {
        return [
            'energy_kcal' => $left['energy_kcal'] + $right['energy_kcal'],
            'protein_mg' => $left['protein_mg'] + $right['protein_mg'],
            'carbohydrate_mg' => $left['carbohydrate_mg'] + $right['carbohydrate_mg'],
            'fat_mg' => $left['fat_mg'] + $right['fat_mg'],
        ];
    }

    /**
     * @return array{energy_kcal: int, protein_mg: int, carbohydrate_mg: int, fat_mg: int}
     */
    public static function zero(): array
    {
        return [
            'energy_kcal' => 0,
            'protein_mg' => 0,
            'carbohydrate_mg' => 0,
            'fat_mg' => 0,
        ];
    }

    public static function scale(int $quantity, int $basisValue, int $divisor): int
    {
        $half = intdiv($divisor, 2);

        if ($quantity > 0 && $basisValue > intdiv(PHP_INT_MAX - $half, $quantity)) {
            throw new NutritionException('invalid_quantity', 'مقدار غذا برای محاسبه بزرگ است.');
        }

        return intdiv($quantity * $basisValue + $half, $divisor);
    }
}
