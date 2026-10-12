<?php

namespace App\Http\Requests\Nutrition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMealPlanRequest extends FormRequest
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
            'title' => [$required, 'string', 'max:160'],
            'days' => [$required, 'array', 'min:1'],
            'days.*.position' => ['required', 'integer', 'min:0', 'max:65535', 'distinct'],
            'days.*.title' => ['required', 'string', 'max:120'],
            'days.*.meals' => ['required', 'array', 'min:1'],
            'days.*.meals.*.position' => ['required', 'integer', 'min:0', 'max:65535'],
            'days.*.meals.*.meal_type' => ['required', Rule::in(['breakfast', 'lunch', 'dinner', 'snack', 'other'])],
            'days.*.meals.*.title' => ['nullable', 'string', 'max:120'],
            'days.*.meals.*.items' => ['required', 'array', 'min:1'],
            'days.*.meals.*.items.*.position' => ['required', 'integer', 'min:0', 'max:65535'],
            'days.*.meals.*.items.*.food_id' => ['required', 'integer', 'min:1'],
            'days.*.meals.*.items.*.quantity_g' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'days.*.meals.*.items.*.quantity_unit_milli' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
