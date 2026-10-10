<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['user_id', 'book_id', 'loaned_at', 'due_at', 'returned_at', 'fine_cents'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loaned_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'fine_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class)->withTrashed();
    }

    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isReturned() && $this->due_at->isPast();
    }

    public function daysLate(): int
    {
        $reference = $this->returned_at ?? Carbon::now();
        $dueDate = $this->due_at->copy()->startOfDay();

        if ($reference->copy()->startOfDay()->lessThanOrEqualTo($dueDate)) {
            return 0;
        }

        return (int) $dueDate->diffInDays($reference->copy()->startOfDay());
    }

    public function calculateFine(): int
    {
        return $this->daysLate() * config()->integer('library.fine_per_day_cents');
    }

    public function markAsReturned(): void
    {
        $this->returned_at = Carbon::now();
        $this->fine_cents = $this->calculateFine();
        $this->save();
    }

    /**
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('returned_at');
    }

    /**
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereNull('returned_at')->where('due_at', '<', Carbon::now());
    }

    /**
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function returned(Builder $query): void
    {
        $query->whereNotNull('returned_at');
    }
}
