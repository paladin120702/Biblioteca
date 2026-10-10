<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Carbon;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    travelTo(Carbon::parse('2026-03-10 09:00:00'));
});

function overdueLoan(string $member, string $book, string $dueAt): Loan
{
    return Loan::factory()
        ->for(User::factory()->state(['name' => $member]))
        ->for(Book::factory()->state(['title' => $book]))
        ->create(['due_at' => Carbon::parse($dueAt)]);
}

it('lists overdue loans with days late and expected fine', function (): void {
    overdueLoan('Ana', 'Dom Casmurro', '2026-03-07 10:00:00');
    overdueLoan('Bruno', 'Iracema', '2026-03-09 10:00:00');
    Loan::factory()->create(['due_at' => Carbon::parse('2026-03-20')]);
    Loan::factory()->create(['due_at' => Carbon::parse('2026-03-01'), 'returned_at' => Carbon::parse('2026-03-05')]);

    artisan('loans:overdue')
        ->expectsTable(
            ['Member', 'Book', 'Due date', 'Days late', 'Expected fine'],
            [
                ['Ana', 'Dom Casmurro', '2026-03-07', 3, "R\$\u{00A0}3,00"],
                ['Bruno', 'Iracema', '2026-03-09', 1, "R\$\u{00A0}1,00"],
            ],
        )
        ->assertSuccessful();
});

it('filters by member', function (): void {
    $loan = overdueLoan('Ana', 'Dom Casmurro', '2026-03-07 10:00:00');
    overdueLoan('Bruno', 'Iracema', '2026-03-09 10:00:00');

    artisan('loans:overdue', ['--user' => $loan->user_id])
        ->expectsTable(
            ['Member', 'Book', 'Due date', 'Days late', 'Expected fine'],
            [['Ana', 'Dom Casmurro', '2026-03-07', 3, "R\$\u{00A0}3,00"]],
        )
        ->assertSuccessful();
});

it('says when there are no overdue loans', function (): void {
    Loan::factory()->create();

    artisan('loans:overdue')
        ->expectsOutput('No overdue loans.')
        ->assertSuccessful();
});

it('fails for an unknown member', function (): void {
    artisan('loans:overdue', ['--user' => 999])
        ->expectsOutput('User [999] not found.')
        ->assertFailed();
});
