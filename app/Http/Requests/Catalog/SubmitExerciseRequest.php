<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\CatalogRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitExerciseRequest extends FormRequest
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
            'proposed_name' => ['required', 'string', 'max:160'],
            'instructions' => ['required', 'string', 'min:1'],
            'goal' => ['required', 'string', 'min:1'],
            'tracking_type' => ['required', Rule::in(CatalogRules::TRACKING_TYPES)],
        ];
    }
}
