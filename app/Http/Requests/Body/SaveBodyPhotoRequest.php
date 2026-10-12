<?php

namespace App\Http\Requests\Body;

use Illuminate\Foundation\Http\FormRequest;

class SaveBodyPhotoRequest extends FormRequest
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
            'photo' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'taken_at' => ['required', 'string'],
            'coaching_relationship_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
