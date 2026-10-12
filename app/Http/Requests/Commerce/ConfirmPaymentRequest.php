<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPaymentRequest extends FormRequest
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
            'attempt_id' => ['required', 'integer'],
            'amount_irr' => ['required', 'integer', 'min:0'],
            'gateway_reference' => ['required', 'string', 'max:191'],
        ];
    }
}
