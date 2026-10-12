<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
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
            'purpose' => ['required', 'string', 'in:signup,login,invitation'],
            'code' => ['required', 'string', 'regex:/^\d{5}$/'],
            'client' => ['required_if:purpose,login', 'nullable', 'string', 'in:web,mobile'],
            'invitation_id' => ['required_if:purpose,invitation', 'nullable', 'integer'],
        ];
    }
}
