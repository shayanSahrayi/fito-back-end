<?php

namespace App\Http\Requests\Nutrition;

use Illuminate\Foundation\Http\FormRequest;

class AssignMealRequest extends FormRequest
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
            'coaching_relationship_id' => ['required', 'integer', 'min:1'],
            'meal_plan_version_id' => ['required', 'integer', 'min:1'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
