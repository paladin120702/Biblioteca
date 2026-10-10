<?php

declare(strict_types=1);

use App\Models\Loan;
use Illuminate\Support\Carbon;

use function Pest\Laravel\travel;
use function Pest\Laravel\travelTo;

function loanDueOnJanuary15(): Loan
{
    return new Loan([
        'loaned_at' => Carbon::parse('2026-01-01 10:00:00'),
        'due_at' => Carbon::parse('2026-01-15 10:00:00'),
    ]);
}

beforeEach(function (): void {
    travelTo(Carbon::parse('2026-01-01 10:00:00'));
});

describe('daysLate', function (): void {
    it('is zero before the due date', function (): void {
        travel(10)->days();

        expect(loanDueOnJanuary15()->daysLate())->toBe(0);
    });

    it('is zero on the due date, even after the due time', function (): void {
        travelTo(Carbon::parse('2026-01-15 23:00:00'));

        expect(loanDueOnJanuary15()->daysLate())->toBe(0);
    });

    it('counts each calendar day after the due date', function (): void {
        travelTo(Carbon::parse('2026-01-18 08:00:00'));

        expect(loanDueOnJanuary15()->daysLate())->toBe(3);
    });

    it('uses the return date for returned loans', function (): void {
        $loan = loanDueOnJanuary15();
        $loan->returned_at = Carbon::parse('2026-01-17 12:00:00');

        travel(30)->days();

        expect($loan->daysLate())->toBe(2);
    });
});

describe('calculateFine', function (): void {
    it('is zero when the loan is not late', function (): void {
        expect(loanDueOnJanuary15()->calculateFine())->toBe(0);
    });

    it('charges the configured amount per day late', function (): void {
        travelTo(Carbon::parse('2026-01-20 09:00:00'));

        $loan = loanDueOnJanuary15();

        expect($loan->calculateFine())->toBe(500);

        config(['library.fine_per_day_cents' => 250]);

        expect($loan->calculateFine())->toBe(1250);
    });
});
