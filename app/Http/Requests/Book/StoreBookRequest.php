<?php

declare(strict_types=1);

namespace App\Http\Requests\Book;

use App\Models\Book;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Book::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'digits:13', 'unique:books,isbn'],
            'author_id' => ['required', 'integer', 'exists:authors,id'],
            'published_year' => ['required', 'integer', 'min:1000', 'max:'.now()->year],
            'copies_total' => ['required', 'integer', 'min:1'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }
}
