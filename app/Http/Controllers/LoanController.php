<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Http\Requests\Loan\IndexLoanRequest;
use App\Http\Requests\Loan\StoreLoanRequest;
use App\Http\Resources\LoanResource;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanController extends Controller
{
    public function index(IndexLoanRequest $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        $loans = Loan::query()
            ->with(['user', 'book' => fn (BelongsTo $book) => $book->withActiveLoansCount()])
            ->unless($user->isAdmin(), fn (Builder $query) => $query->whereBelongsTo($user))
            ->when($request->enum('status', LoanStatus::class), fn (Builder $query, LoanStatus $status) => match ($status) {
                LoanStatus::Active => $query->active(),
                LoanStatus::Overdue => $query->overdue(),
                LoanStatus::Returned => $query->returned(),
            })
            ->latest('loaned_at')
            ->paginate(15)
            ->withQueryString();

        return LoanResource::collection($loans);
    }

    public function store(StoreLoanRequest $request, #[CurrentUser] User $user): LoanResource
    {
        $loan = DB::transaction(function () use ($request, $user): Loan {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $book = Book::lockForUpdate()->findOrFail($request->integer('book_id'));

            $denial = $user->borrowDenial($book);

            if ($denial !== null) {
                throw ValidationException::withMessages(['book_id' => [$denial->message()]]);
            }

            return $user->borrow($book);
        });

        return new LoanResource($loan->load(['user', 'book']));
    }

    public function return(Loan $loan): LoanResource
    {
        $this->authorize('return', $loan);

        $loan = DB::transaction(function () use ($loan): Loan {
            Book::withTrashed()->lockForUpdate()->findOrFail($loan->book_id);
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);

            abort_if($loan->isReturned(), 409, 'This loan has already been returned.');

            $loan->markAsReturned();

            return $loan;
        });

        return new LoanResource($loan->load(['user', 'book']));
    }
}
