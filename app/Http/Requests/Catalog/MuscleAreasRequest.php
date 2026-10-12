<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class MuscleAreasRequest extends FormRequest
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
            'body_model_area_ids' => ['present', 'array'],
            'body_model_area_ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }
}
