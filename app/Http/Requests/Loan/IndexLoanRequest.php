<?php

declare(strict_types=1);

namespace App\Http\Requests\Loan;

use App\Enums\LoanStatus;
use App\Models\Loan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Loan::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(LoanStatus::class)],
        ];
    }
}
