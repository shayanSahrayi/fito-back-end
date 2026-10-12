<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CompleteSignupRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:20'],
            'national_code' => ['required', 'string', 'max:20'],
            'display_name' => ['required', 'string', 'max:120'],
            'panel' => ['required', 'string', 'in:coach,athlete'],
        ];
    }
}
