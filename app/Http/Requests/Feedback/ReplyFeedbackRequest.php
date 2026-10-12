<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplyFeedbackRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:2000'],
            'pain_level' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'visibility' => ['sometimes', 'nullable', Rule::in(['shared', 'coach_only'])],
        ];
    }
}
