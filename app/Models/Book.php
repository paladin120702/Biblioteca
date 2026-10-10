<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * @property-read int|null $active_loans_count
 */
#[Fillable(['title', 'isbn', 'author_id', 'published_year', 'copies_total'])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_year' => 'integer',
            'copies_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
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

    public function availableCopies(): int
    {
        $activeLoans = $this->active_loans_count
            ?? $this->activeLoans()->count();

        return max(0, $this->copies_total - $activeLoans);
    }

    public function isAvailable(): bool
    {
        return $this->availableCopies() > 0;
    }

    public function hasActiveLoans(): bool
    {
        return $this->activeLoans()->exists();
    }

    /**
     * @param  Builder<Book>  $query
     */
    #[Scope]
    protected function withActiveLoansCount(Builder $query): void
    {
        $query->withCount('activeLoans');
    }

    /**
     * @param  Builder<Book>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->has('activeLoans', '<', DB::raw('books.copies_total'));
    }

    /**
     * @param  Builder<Book>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->where(function (Builder $query) use ($term): void {
            $query->where('title', 'like', "%{$term}%")
                ->orWhereHas('author', fn (Builder $author) => $author->where('name', 'like', "%{$term}%"));
        });
    }

    /**
     * @param  Builder<Book>  $query
     */
    #[Scope]
    protected function inCategory(Builder $query, int $categoryId): void
    {
        $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey($categoryId));
    }
}
