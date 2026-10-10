<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $loanedAt = Carbon::now();

        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'loaned_at' => $loanedAt,
            'due_at' => $loanedAt->copy()->addDays(config()->integer('library.loan_days')),
            'returned_at' => null,
            'fine_cents' => 0,
        ];
    }
}
