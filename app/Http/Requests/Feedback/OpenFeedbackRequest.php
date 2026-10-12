<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenFeedbackRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:2000'],
            'pain_level' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'visibility' => ['sometimes', 'nullable', Rule::in(['shared', 'coach_only'])],
            'workout_session_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'workout_day_exercise_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'exercise_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
