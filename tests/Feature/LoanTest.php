<?php

declare(strict_types=1);

use App\Enums\LoanDenial;
use App\Models\Author;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertNotSoftDeleted;
use function Pest\Laravel\travel;

describe('borrowing', function (): void {
    it('creates a loan due in the configured number of days (rule 4)', function (): void {
        $member = User::factory()->create();
        $book = Book::factory()->create();

        actingAs($member)->postJson('/api/loans', ['book_id' => $book->id])
            ->assertCreated()
            ->assertJsonPath('data.book.id', $book->id)
            ->assertJsonPath('data.book.available_copies', $book->copies_total - 1)
            ->assertJsonPath('data.is_overdue', false);

        $loan = Loan::sole();

        expect($loan->user_id)->toBe($member->id)
            ->and($loan->returned_at)->toBeNull()
            ->and($loan->due_at->equalTo($loan->loaned_at->copy()->addDays(14)))->toBeTrue();
    });

    it('limits the number of active loans per member (rule 1)', function (): void {
        $member = User::factory()->create();
        Loan::factory(3)->for($member)->create();
        Loan::factory()->for($member)->create(['returned_at' => now()]);

        actingAs($member)->postJson('/api/loans', ['book_id' => Book::factory()->create()->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.book_id.0', LoanDenial::LoanLimitReached->message());
    });

    it('requires an available copy (rule 2)', function (): void {
        $book = Book::factory()->create(['copies_total' => 1]);
        Loan::factory()->for($book)->create();

        actingAs(User::factory()->create())->postJson('/api/loans', ['book_id' => $book->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.book_id.0', LoanDenial::Unavailable->message());
    });

    it('frees the copy again after it is returned (rule 2)', function (): void {
        $book = Book::factory()->create(['copies_total' => 1]);
        Loan::factory()->for($book)->create(['returned_at' => now()]);

        actingAs(User::factory()->create())->postJson('/api/loans', ['book_id' => $book->id])
            ->assertCreated();
    });

    it('does not lend the same book twice to the same member (rule 3)', function (): void {
        $member = User::factory()->create();
        $book = Book::factory()->create(['copies_total' => 5]);
        Loan::factory()->for($member)->for($book)->create();

        actingAs($member)->postJson('/api/loans', ['book_id' => $book->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.book_id.0', LoanDenial::AlreadyBorrowed->message());
    });

    it('blocks members with overdue loans (rule 6)', function (): void {
        $member = User::factory()->create();
        Loan::factory()->for($member)->create(['due_at' => now()->subDay()]);

        actingAs($member)->postJson('/api/loans', ['book_id' => Book::factory()->create()->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.book_id.0', LoanDenial::HasOverdueLoans->message());
    });

    it('validates the book', function (): void {
        $deleted = Book::factory()->create();
        $deleted->delete();

        asMember()->postJson('/api/loans', ['book_id' => 999])->assertJsonValidationErrors(['book_id']);
        asMember()->postJson('/api/loans', ['book_id' => $deleted->id])->assertJsonValidationErrors(['book_id']);
    });

    it('only lets members borrow', function (): void {
        asAdmin()->postJson('/api/loans', ['book_id' => Book::factory()->create()->id])->assertForbidden();
    });
});

describe('returning', function (): void {
    it('records the return date with no fine when on time', function (): void {
        $loan = Loan::factory()->create();

        travel(14)->days();

        actingAs($loan->user)->patchJson("/api/loans/{$loan->id}/return")
            ->assertOk()
            ->assertJsonPath('data.fine_cents', 0)
            ->assertJsonPath('data.is_overdue', false);

        expect($loan->fresh()?->returned_at)->not->toBeNull();
    });

    it('charges a fine per day late (rule 5)', function (): void {
        $loan = Loan::factory()->create();

        travel(16)->days();

        actingAs($loan->user)->patchJson("/api/loans/{$loan->id}/return")
            ->assertOk()
            ->assertJsonPath('data.days_late', 2)
            ->assertJsonPath('data.fine_cents', 200)
            ->assertJsonPath('data.fine', "R\$\u{00A0}2,00");

        assertDatabaseHas('loans', ['id' => $loan->id, 'fine_cents' => 200]);
    });

    it('rejects returning a loan twice', function (): void {
        $loan = Loan::factory()->create(['returned_at' => now()]);

        actingAs($loan->user)->patchJson("/api/loans/{$loan->id}/return")->assertConflict();
    });

    it('only lets the owner or an admin return a loan (rule 8)', function (): void {
        $loan = Loan::factory()->create();

        asMember()->patchJson("/api/loans/{$loan->id}/return")->assertForbidden();
        asAdmin()->patchJson("/api/loans/{$loan->id}/return")->assertOk();
    });
});

describe('listing', function (): void {
    it('shows members only their own loans (rule 8)', function (): void {
        $member = User::factory()->create();
        $own = Loan::factory()->for($member)->create();
        Loan::factory()->create();

        actingAs($member)->getJson('/api/loans')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    });

    it('shows admins every loan (rule 8)', function (): void {
        Loan::factory(3)->create();

        asAdmin()->getJson('/api/loans')->assertOk()->assertJsonCount(3, 'data');
    });

    it('filters by status', function (string $status, string $expected): void {
        $loans = [
            'active' => Loan::factory()->create(),
            'overdue' => Loan::factory()->create(['due_at' => now()->subDay()]),
            'returned' => Loan::factory()->create(['returned_at' => now()]),
        ];

        asAdmin()->getJson("/api/loans?status={$status}")
            ->assertOk()
            ->assertJsonCount($status === 'active' ? 2 : 1, 'data')
            ->assertJsonFragment(['id' => $loans[$expected]->id]);
    })->with([
        ['active', 'active'],
        ['overdue', 'overdue'],
        ['returned', 'returned'],
    ]);

    it('rejects an unknown status', function (): void {
        asAdmin()->getJson('/api/loans?status=lost')->assertJsonValidationErrors(['status']);
    });
});

describe('catalog integrity', function (): void {
    it('lets only admins manage the catalog (rule 7)', function (): void {
        asMember()->postJson('/api/books', [])->assertForbidden();
        asAdmin()->postJson('/api/books', [])->assertUnprocessable();
    });

    it('does not delete a book with active loans (rule 9)', function (): void {
        $loan = Loan::factory()->create();

        asAdmin()->deleteJson("/api/books/{$loan->book_id}")->assertConflict();

        assertNotSoftDeleted('books', ['id' => $loan->book_id]);

        $loan->update(['returned_at' => Carbon::now()]);

        asAdmin()->deleteJson("/api/books/{$loan->book_id}")->assertNoContent();
    });

    it('does not delete an author with books', function (): void {
        $author = Author::factory()->has(Book::factory())->create();

        asAdmin()->deleteJson("/api/authors/{$author->id}")->assertConflict();
    });
});
