<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWorkoutSessionRequest extends FormRequest
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
            'source' => ['required', Rule::in(['prescribed', 'athlete_free'])],
            'performed_on' => ['required', 'date_format:Y-m-d'],
            'workout_assignment_id' => [
                Rule::requiredIf(fn (): bool => $this->input('source') === 'prescribed'),
                Rule::prohibitedIf(fn (): bool => $this->input('source') === 'athlete_free'),
                'nullable',
                'integer',
                'min:1',
            ],
            'workout_day_id' => [
                Rule::prohibitedIf(fn (): bool => $this->input('source') === 'athlete_free'),
                'nullable',
                'integer',
                'min:1',
            ],
            'coaching_relationship_id' => [
                Rule::prohibitedIf(fn (): bool => $this->input('source') === 'prescribed'),
                'nullable',
                'integer',
                'min:1',
            ],
            'perceived_effort' => ['nullable', 'integer', 'min:1', 'max:10'],
            'athlete_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
