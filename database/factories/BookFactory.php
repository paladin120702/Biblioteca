<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Author;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->sentence(3), '.'),
            'isbn' => fake()->unique()->isbn13(),
            'author_id' => Author::factory(),
            'published_year' => fake()->numberBetween(1900, (int) date('Y')),
            'copies_total' => fake()->numberBetween(1, 5),
        ];
    }
}
