<?php

namespace App\Domain\Commerce;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class PlanWriter
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $attributes = $this->attributes($data);
        $features = $this->features($data['features'] ?? []);
        FeatureCatalog::assertQuota($attributes['ai_request_quota'], $features);

        try {
            $plan = DB::transaction(function () use ($attributes, $features): Plan {
                $plan = Plan::query()->create($attributes);
                $this->replaceFeatures($plan, $features);

                return $plan;
            });
        } catch (UniqueConstraintViolationException) {
            throw new CommerceException('code_taken', 'این کد پلن قبلاً ثبت شده است.');
        }

        return $this->present($plan->refresh());
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function update(Plan $plan, array $changes): array
    {
        $plan->load('features');
        $features = array_key_exists('features', $changes)
            ? $this->features($changes['features'])
            : $plan->features->pluck('feature_key')->values()->all();
        $quota = array_key_exists('ai_request_quota', $changes)
            ? $this->quota($changes['ai_request_quota'])
            : $plan->ai_request_quota;
        FeatureCatalog::assertQuota($quota, $features);

        $attributes = [];

        foreach (['code', 'name', 'description', 'billing_interval_unit'] as $field) {
            if (array_key_exists($field, $changes)) {
                $attributes[$field] = $changes[$field];
            }
        }

        if (array_key_exists('price_irr', $changes)) {
            $attributes['price_irr'] = $this->nonNegative($changes['price_irr'], 'invalid_price', 'مبلغ پلن معتبر نیست.');
        }

        if (array_key_exists('billing_interval_count', $changes)) {
            $attributes['billing_interval_count'] = $this->positive($changes['billing_interval_count']);
        }

        if (array_key_exists('athlete_capacity', $changes)) {
            $attributes['athlete_capacity'] = $this->nonNegative($changes['athlete_capacity'], 'invalid_capacity', 'ظرفیت ورزشکار معتبر نیست.');
        }

        if (array_key_exists('billing_interval_unit', $changes) || array_key_exists('billing_interval_count', $changes)) {
            $unit = $attributes['billing_interval_unit'] ?? $plan->billing_interval_unit;
            $count = $attributes['billing_interval_count'] ?? $plan->billing_interval_count;
            BillingPeriod::end(now(), $unit, (int) $count);
        }

        if (array_key_exists('ai_request_quota', $changes)) {
            $attributes['ai_request_quota'] = $quota;
        }

        if (array_key_exists('is_sellable', $changes)) {
            $attributes['is_sellable'] = (bool) $changes['is_sellable'];
        }

        try {
            DB::transaction(function () use ($plan, $attributes, $changes, $features): void {
                if ($attributes !== []) {
                    $plan->fill($attributes)->save();
                }

                if (array_key_exists('features', $changes)) {
                    $this->replaceFeatures($plan, $features);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw new CommerceException('code_taken', 'این کد پلن قبلاً ثبت شده است.');
        }

        return $this->present($plan->refresh());
    }

    public function delete(Plan $plan): void
    {
        if ($plan->orders()->exists()) {
            throw new CommerceException('plan_ordered', 'پلن فروخته‌شده حذف نمی‌شود.');
        }

        DB::transaction(function () use ($plan): void {
            $plan->features()->delete();
            $plan->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Plan $plan): array
    {
        $plan->loadMissing('features');

        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_irr' => (int) $plan->price_irr,
            'billing_interval_unit' => $plan->billing_interval_unit,
            'billing_interval_count' => (int) $plan->billing_interval_count,
            'athlete_capacity' => (int) $plan->athlete_capacity,
            'ai_request_quota' => $plan->ai_request_quota === null ? null : (int) $plan->ai_request_quota,
            'is_sellable' => (bool) $plan->is_sellable,
            'features' => $plan->features->pluck('feature_key')->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $unit = (string) $data['billing_interval_unit'];
        $count = $this->positive($data['billing_interval_count']);
        BillingPeriod::end(now(), $unit, $count);

        return [
            'code' => trim((string) $data['code']),
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'price_irr' => $this->nonNegative($data['price_irr'], 'invalid_price', 'مبلغ پلن معتبر نیست.'),
            'billing_interval_unit' => $unit,
            'billing_interval_count' => $count,
            'athlete_capacity' => $this->nonNegative($data['athlete_capacity'], 'invalid_capacity', 'ظرفیت ورزشکار معتبر نیست.'),
            'ai_request_quota' => $this->quota($data['ai_request_quota'] ?? null),
            'is_sellable' => (bool) $data['is_sellable'],
        ];
    }

    /**
     * @param  mixed  $features
     * @return list<string>
     */
    private function features(mixed $features): array
    {
        if (! is_array($features)) {
            throw new CommerceException('invalid_feature', 'قابلیت پلن شناخته‌شده نیست.');
        }

        $keys = array_values(array_map(fn (mixed $key): string => (string) $key, $features));
        FeatureCatalog::assertValid($keys);

        return $keys;
    }

    private function quota(mixed $quota): ?int
    {
        if ($quota === null) {
            return null;
        }

        $quota = $this->nonNegative($quota, 'invalid_quota', 'سهمیه هوش مصنوعی معتبر نیست.');

        return $quota < 1 ? null : $quota;
    }

    private function positive(mixed $value): int
    {
        $value = $this->nonNegative($value, 'invalid_interval', 'دوره پلن معتبر نیست.');

        if ($value < 1) {
            throw new CommerceException('invalid_interval', 'دوره پلن معتبر نیست.');
        }

        return $value;
    }

    private function nonNegative(mixed $value, string $code, string $message): int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            throw new CommerceException($code, $message);
        }

        return (int) $value;
    }

    /**
     * @param  list<string>  $features
     */
    private function replaceFeatures(Plan $plan, array $features): void
    {
        PlanFeature::query()->where('plan_id', $plan->id)->delete();

        foreach ($features as $key) {
            PlanFeature::query()->create([
                'plan_id' => $plan->id,
                'feature_key' => $key,
            ]);
        }
    }
}
