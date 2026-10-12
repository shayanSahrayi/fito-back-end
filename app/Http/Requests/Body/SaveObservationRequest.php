<?php

namespace App\Http\Requests\Body;

use Illuminate\Foundation\Http\FormRequest;

class SaveObservationRequest extends FormRequest
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
            'measured_at' => ['required', 'string'],
            'source' => ['sometimes', 'nullable', 'string'],
            'visibility' => ['sometimes', 'nullable', 'string'],
            'coaching_relationship_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'metrics' => ['sometimes', 'nullable', 'array'],
            'metrics.*' => ['nullable', 'numeric'],
        ];
    }
}
