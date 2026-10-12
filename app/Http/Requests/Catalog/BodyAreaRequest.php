<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class BodyAreaRequest extends FormRequest
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
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'string', 'max:64'],
            'name_fa' => [$required, 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
        ];
    }
}
