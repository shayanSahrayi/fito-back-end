<?php

namespace App\Http\Requests\Workout;

use App\Domain\Catalog\CatalogRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWorkoutProgramRequest extends FormRequest
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
            'note' => ['nullable', 'string'],
            'days' => [$required, 'array', 'min:1'],
            'days.*.position' => ['required', 'integer', 'min:0', 'max:65535', 'distinct'],
            'days.*.title' => ['required', 'string', 'max:120'],
            'days.*.weekday' => ['nullable', 'integer', 'min:0', 'max:6'],
            'days.*.exercises' => ['required', 'array', 'min:1'],
            'days.*.exercises.*.position' => ['required', 'integer', 'min:0', 'max:65535'],
            'days.*.exercises.*.exercise_id' => ['required', 'integer', 'min:1'],
            'days.*.exercises.*.tracking_type' => ['required', Rule::in(CatalogRules::TRACKING_TYPES)],
            'days.*.exercises.*.prescribed_sets' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'days.*.exercises.*.reps_min' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'days.*.exercises.*.reps_max' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'days.*.exercises.*.target_weight_g' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'days.*.exercises.*.rest_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'days.*.exercises.*.duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'days.*.exercises.*.distance_m' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'days.*.exercises.*.coach_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
