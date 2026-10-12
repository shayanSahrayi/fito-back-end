<?php

namespace App\Http\Requests\Nutrition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviseFoodRequest extends FormRequest
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
        return [
            'basis' => ['required', Rule::in(['per_100g', 'per_unit'])],
            'unit_code' => ['required', 'string', 'max:16'],
            'unit_label' => ['required', 'string', 'max:40'],
            'grams_per_unit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'energy_kcal' => ['required', 'integer', 'min:0'],
            'protein_mg' => ['required', 'integer', 'min:0'],
            'carbohydrate_mg' => ['required', 'integer', 'min:0'],
            'fat_mg' => ['required', 'integer', 'min:0'],
        ];
    }
}
