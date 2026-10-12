<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\CatalogRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveExerciseRequest extends FormRequest
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
            'muscles' => ['required', 'array', 'min:1'],
            'muscles.*.muscle_id' => ['required', 'integer', 'distinct', 'min:1'],
            'muscles.*.involvement' => ['required', Rule::in(CatalogRules::INVOLVEMENTS)],
            'media' => ['sometimes', 'array'],
            'media.*.media_type' => ['required', Rule::in(CatalogRules::MEDIA_TYPES)],
            'media.*.path' => ['required', 'string', 'max:255'],
            'media.*.sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
