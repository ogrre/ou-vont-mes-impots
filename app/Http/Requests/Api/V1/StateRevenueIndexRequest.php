<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StateRevenueIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->input('year', 2025),
            'status' => $this->input('status', 'revised_estimate'),
        ]);
    }

    /** @return array<string, array<int, string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'status' => ['required', Rule::in(['initial_estimate', 'revised_estimate', 'budget_bill'])],
        ];
    }
}
