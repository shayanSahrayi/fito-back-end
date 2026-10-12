<?php

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;

class AddProfileRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:120'],
            'panel' => ['required', 'string', 'in:coach,athlete'],
        ];
    }
}
