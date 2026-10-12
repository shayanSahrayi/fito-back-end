<?php

namespace App\Http\Requests\Coaching;

use Illuminate\Foundation\Http\FormRequest;

class RegisterAthleteRequest extends FormRequest
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
        ];
    }
}
