<?php

namespace App\Http\Requests\Commerce;

use App\Domain\Commerce\FeatureCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'string', 'max:64'],
            'name' => [$required, 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'price_irr' => [$required, 'integer', 'min:0'],
            'billing_interval_unit' => [$required, 'in:day,month'],
            'billing_interval_count' => [$required, 'integer', 'min:1'],
            'athlete_capacity' => [$required, 'integer', 'min:0'],
            'ai_request_quota' => ['nullable', 'integer', 'min:1'],
            'is_sellable' => [$required, 'boolean'],
            'features' => [$this->isMethod('post') ? 'present' : 'sometimes', 'array'],
            'features.*' => ['string', 'distinct', Rule::in(FeatureCatalog::KEYS)],
        ];
    }
}
