<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

#[Signature('loans:overdue {--user= : Only show loans of the member with this ID}')]
#[Description('List overdue loans with days late and expected fine')]
class ListOverdueLoans extends Command
{
    public function handle(): int
    {
        $userId = $this->option('user');
        $user = null;

        if ($userId !== null) {
            $user = User::find($userId);

            if ($user === null) {
                $this->error("User [{$userId}] not found.");

                return self::FAILURE;
            }
        }

        $loans = Loan::query()
            ->overdue()
            ->with(['user', 'book'])
            ->when($user, fn (Builder $query, User $user) => $query->whereBelongsTo($user))
            ->orderBy('due_at')
            ->get();

        if ($loans->isEmpty()) {
            $this->info('No overdue loans.');

            return self::SUCCESS;
        }

        $this->table(
            ['Member', 'Book', 'Due date', 'Days late', 'Expected fine'],
            $loans->map(fn (Loan $loan): array => [
                $loan->user->name,
                $loan->book->title,
                $loan->due_at->format('Y-m-d'),
                $loan->daysLate(),
                Number::currency($loan->calculateFine() / 100, in: 'BRL', locale: 'pt_BR'),
            ]),
        );

        return self::SUCCESS;
    }
}
