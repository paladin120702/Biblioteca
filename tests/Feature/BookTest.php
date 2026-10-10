<?php

declare(strict_types=1);

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Loan;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\getJson;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bookPayload(array $overrides = []): array
{
    return [
        'title' => 'Dom Casmurro',
        'isbn' => '9788535910663',
        'author_id' => Author::factory()->create()->id,
        'published_year' => 1899,
        'copies_total' => 3,
        ...$overrides,
    ];
}

describe('permissions', function (): void {
    it('requires authentication', function (): void {
        getJson('/api/books')->assertUnauthorized();
    });

    it('lets any authenticated user list and view books', function (): void {
        $book = Book::factory()->create();

        asMember()->getJson('/api/books')->assertOk()->assertJsonPath('data.0.id', $book->id);
        asMember()->getJson("/api/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('data.author.id', $book->author_id);
    });

    it('lets an admin create a book with categories', function (): void {
        $categories = Category::factory(2)->create();

        asAdmin()->postJson('/api/books', bookPayload(['categories' => $categories->pluck('id')->all()]))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Dom Casmurro')
            ->assertJsonPath('data.available_copies', 3)
            ->assertJsonCount(2, 'data.categories');

        assertDatabaseHas('books', ['isbn' => '9788535910663']);
    });

    it('lets an admin update a book and replace its categories', function (): void {
        $book = Book::factory()->hasAttached(Category::factory())->create();
        $category = Category::factory()->create();

        asAdmin()->patchJson("/api/books/{$book->id}", ['title' => 'Memórias Póstumas', 'categories' => [$category->id]])
            ->assertOk()
            ->assertJsonPath('data.title', 'Memórias Póstumas')
            ->assertJsonPath('data.categories.0.id', $category->id)
            ->assertJsonCount(1, 'data.categories');
    });

    it('lets an admin soft delete a book', function (): void {
        $book = Book::factory()->create();

        asAdmin()->deleteJson("/api/books/{$book->id}")->assertNoContent();

        assertSoftDeleted($book);
        asAdmin()->getJson("/api/books/{$book->id}")->assertNotFound();
    });

    it('forbids members from writing books', function (): void {
        $book = Book::factory()->create();

        asMember()->postJson('/api/books', bookPayload())->assertForbidden();
        asMember()->putJson("/api/books/{$book->id}", ['title' => 'Other'])->assertForbidden();
        asMember()->deleteJson("/api/books/{$book->id}")->assertForbidden();
    });
});

describe('validation', function (): void {
    it('requires the book fields', function (): void {
        asAdmin()->postJson('/api/books', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'isbn', 'author_id', 'published_year', 'copies_total']);
    });

    it('rejects invalid values', function (): void {
        Book::factory()->create(['isbn' => '9788535910663']);

        asAdmin()->postJson('/api/books', bookPayload([
            'author_id' => 999,
            'copies_total' => 0,
            'published_year' => now()->year + 1,
            'categories' => [999],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['isbn', 'author_id', 'copies_total', 'published_year', 'categories.0']);
    });
});

describe('filters', function (): void {
    it('paginates 15 books per page', function (): void {
        Book::factory(20)->create();

        asMember()->getJson('/api/books')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20);
    });

    it('searches by title or author name', function (): void {
        $byTitle = Book::factory()->create(['title' => 'O Cortiço']);
        $byAuthor = Book::factory()->for(Author::factory()->state(['name' => 'Aluísio Cortiço']))->create();
        Book::factory()->create(['title' => 'Iracema']);

        $ids = asMember()->getJson('/api/books?search=Cortiço')->assertOk()->json('data.*.id');

        expect($ids)->toEqualCanonicalizing([$byTitle->id, $byAuthor->id]);
    });

    it('filters by category', function (): void {
        $category = Category::factory()->create();
        $book = Book::factory()->hasAttached($category)->create();
        Book::factory()->create();

        asMember()->getJson("/api/books?category={$category->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $book->id);
    });

    it('filters available books and counts available copies', function (): void {
        $available = Book::factory()->create(['copies_total' => 2]);
        Loan::factory()->for($available)->create();
        Loan::factory()->for($available)->create(['returned_at' => now()]);

        $unavailable = Book::factory()->create(['copies_total' => 1]);
        Loan::factory()->for($unavailable)->create();

        asMember()->getJson('/api/books?available=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $available->id)
            ->assertJsonPath('data.0.available_copies', 1);
    });
});
