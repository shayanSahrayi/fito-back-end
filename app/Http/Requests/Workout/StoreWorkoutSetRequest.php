<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkoutSetRequest extends FormRequest
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
            'workout_day_exercise_id' => ['nullable', 'integer', 'min:1'],
            'exercise_id' => ['nullable', 'integer', 'min:1'],
            'set_index' => ['required', 'integer', 'min:0', 'max:65535'],
            'reps' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'weight_g' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'distance_m' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_completed' => ['sometimes', 'boolean'],
        ];
    }
}
