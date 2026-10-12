<?php

namespace App\Http\Requests\Coaching;

use Illuminate\Foundation\Http\FormRequest;

class EndRelationshipRequest extends FormRequest
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
            'end_reason' => ['nullable', 'string', 'max:160'],
        ];
    }
}
