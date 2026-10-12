<?php

namespace App\Domain\Catalog;

use App\Models\BodyModelArea;
use App\Models\Muscle;
use Illuminate\Database\UniqueConstraintViolationException;

final class AnatomyWriter
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createMuscle(array $data): array
    {
        $parentId = $this->parentId($data['parent_id'] ?? null);
        $this->assertParent($parentId, null);

        try {
            $muscle = Muscle::query()->create([
                'code' => $this->code($data['code'] ?? null),
                'name_fa' => CatalogRules::boundedText($data['name_fa'] ?? null, 120, 'invalid_name', 'نام عضله معتبر نیست.'),
                'name_en' => CatalogRules::optionalText($data['name_en'] ?? null, 120),
                'parent_id' => $parentId,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new CatalogException('code_taken', 'این کد قبلاً ثبت شده است.');
        }

        return $this->presentMuscle($muscle);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateMuscle(Muscle $muscle, array $data): array
    {
        if (array_key_exists('code', $data)) {
            $muscle->code = $this->code($data['code']);
        }

        if (array_key_exists('name_fa', $data)) {
            $muscle->name_fa = CatalogRules::boundedText($data['name_fa'], 120, 'invalid_name', 'نام عضله معتبر نیست.');
        }

        if (array_key_exists('name_en', $data)) {
            $muscle->name_en = CatalogRules::optionalText($data['name_en'], 120);
        }

        if (array_key_exists('parent_id', $data)) {
            $parentId = $this->parentId($data['parent_id']);
            $this->assertParent($parentId, $muscle->id);
            $muscle->parent_id = $parentId;
        }

        try {
            $muscle->save();
        } catch (UniqueConstraintViolationException) {
            throw new CatalogException('code_taken', 'این کد قبلاً ثبت شده است.');
        }

        return $this->presentMuscle($muscle);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function muscles(): array
    {
        return Muscle::query()
            ->with('bodyAreas')
            ->orderBy('id')
            ->get()
            ->map(fn (Muscle $muscle): array => $this->presentMuscle($muscle))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentMuscle(Muscle $muscle): array
    {
        $muscle->loadMissing('bodyAreas');

        return [
            'id' => $muscle->id,
            'code' => $muscle->code,
            'name_fa' => $muscle->name_fa,
            'name_en' => $muscle->name_en,
            'parent_id' => $muscle->parent_id === null ? null : (int) $muscle->parent_id,
            'areas' => $muscle->bodyAreas
                ->sortBy('id')
                ->map(fn (BodyModelArea $area): array => $this->presentArea($area))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createArea(array $data): array
    {
        try {
            $area = BodyModelArea::query()->create([
                'code' => $this->code($data['code'] ?? null),
                'name_fa' => CatalogRules::boundedText($data['name_fa'] ?? null, 120, 'invalid_name', 'نام ناحیه معتبر نیست.'),
                'name_en' => CatalogRules::optionalText($data['name_en'] ?? null, 120),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new CatalogException('code_taken', 'این کد قبلاً ثبت شده است.');
        }

        return $this->presentArea($area);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateArea(BodyModelArea $area, array $data): array
    {
        if (array_key_exists('code', $data)) {
            $area->code = $this->code($data['code']);
        }

        if (array_key_exists('name_fa', $data)) {
            $area->name_fa = CatalogRules::boundedText($data['name_fa'], 120, 'invalid_name', 'نام ناحیه معتبر نیست.');
        }

        if (array_key_exists('name_en', $data)) {
            $area->name_en = CatalogRules::optionalText($data['name_en'], 120);
        }

        try {
            $area->save();
        } catch (UniqueConstraintViolationException) {
            throw new CatalogException('code_taken', 'این کد قبلاً ثبت شده است.');
        }

        return $this->presentArea($area);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function areas(): array
    {
        return BodyModelArea::query()
            ->orderBy('id')
            ->get()
            ->map(fn (BodyModelArea $area): array => $this->presentArea($area))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentArea(BodyModelArea $area): array
    {
        return [
            'id' => $area->id,
            'code' => $area->code,
            'name_fa' => $area->name_fa,
            'name_en' => $area->name_en,
        ];
    }

    /**
     * @param  list<int>  $areaIds
     * @return array<string, mixed>
     */
    public function syncAreas(Muscle $muscle, array $areaIds): array
    {
        $ids = array_values(array_unique(array_map(intval(...), $areaIds)));
        $found = BodyModelArea::query()->whereIn('id', $ids)->count();

        if ($found !== count($ids)) {
            throw new CatalogException('area_not_found', 'ناحیه مدل بدن پیدا نشد.');
        }

        $muscle->bodyAreas()->sync($ids);
        $muscle->unsetRelation('bodyAreas');

        return $this->presentMuscle($muscle);
    }

    private function code(mixed $value): string
    {
        return CatalogRules::boundedText($value, 64, 'invalid_code', 'کد معتبر نیست.');
    }

    private function parentId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function assertParent(?int $parentId, ?int $selfId): void
    {
        if ($parentId === null) {
            return;
        }

        $current = $parentId;
        $guard = 0;

        while ($current !== null) {
            if ($selfId !== null && $current === $selfId) {
                throw new CatalogException('invalid_parent', 'عضله نمی‌تواند والد خودش باشد.');
            }

            $parent = Muscle::query()->find($current);

            if ($parent === null) {
                throw new CatalogException('muscle_not_found', 'عضله والد پیدا نشد.');
            }

            $current = $parent->parent_id === null ? null : (int) $parent->parent_id;
            $guard++;

            if ($guard > 32) {
                throw new CatalogException('invalid_parent', 'عضله نمی‌تواند والد خودش باشد.');
            }
        }
    }
}
