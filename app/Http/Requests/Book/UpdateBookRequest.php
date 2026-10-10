<?php

declare(strict_types=1);

namespace App\Http\Requests\Book;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('book')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'isbn' => ['sometimes', 'required', 'string', 'digits:13', Rule::unique('books', 'isbn')->ignore($this->route('book'))],
            'author_id' => ['sometimes', 'required', 'integer', 'exists:authors,id'],
            'published_year' => ['sometimes', 'required', 'integer', 'min:1000', 'max:'.now()->year],
            'copies_total' => ['sometimes', 'required', 'integer', 'min:1'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }
}
