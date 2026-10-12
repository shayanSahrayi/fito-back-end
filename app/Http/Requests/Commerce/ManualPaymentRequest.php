<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

class ManualPaymentRequest extends FormRequest
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
            'amount_irr' => ['required', 'integer', 'min:0'],
            'gateway_reference' => ['required', 'string', 'max:191'],
        ];
    }
}
