<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StateExpenditureIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->input('year', 2025),
            'classification' => $this->input('classification', 'mission'),
            'measure' => $this->input('measure', 'cp'),
        ]);
    }

    /** @return array<string, array<int, string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'classification' => ['required', Rule::in(['mission', 'ministry', 'nature'])],
            'measure' => ['required', Rule::in(['ae', 'cp', 'commitment_authorization', 'payment_credit'])],
        ];
    }
}
