<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@biblioteca.test',
        ]);

        User::factory(3)->create();

        $authors = Author::factory(10)->create();
        $categories = Category::factory(5)->create();

        Book::factory(30)
            ->recycle($authors)
            ->create()
            ->each(function (Book $book) use ($categories): void {
                $book->categories()->attach($categories->random(rand(1, 3)));
            });
    }
}
