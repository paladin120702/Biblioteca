<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoanDenial;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => Role::Member->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function activeLoans(): HasMany
    {
        return $this->loans()->active();
    }

    public function hasOverdueLoans(): bool
    {
        return $this->loans()->overdue()->exists();
    }

    public function canBorrow(Book $book): bool
    {
        return $this->borrowDenial($book) === null;
    }

    public function borrowDenial(Book $book): ?LoanDenial
    {
        return match (true) {
            $this->hasOverdueLoans() => LoanDenial::HasOverdueLoans,
            $this->activeLoans()->count() >= config()->integer('library.max_active_loans') => LoanDenial::LoanLimitReached,
            $this->activeLoans()->whereBelongsTo($book)->exists() => LoanDenial::AlreadyBorrowed,
            ! $book->isAvailable() => LoanDenial::Unavailable,
            default => null,
        };
    }

    public function borrow(Book $book): Loan
    {
        $loanedAt = Carbon::now();

        return $this->loans()->create([
            'book_id' => $book->id,
            'loaned_at' => $loanedAt,
            'due_at' => $loanedAt->copy()->addDays(config()->integer('library.loan_days')),
        ]);
    }
}
