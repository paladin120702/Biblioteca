<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Book\IndexBookRequest;
use App\Http\Requests\Book\StoreBookRequest;
use App\Http\Requests\Book\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $books = Book::query()
            ->with(['author', 'categories'])
            ->withActiveLoansCount()
            ->when($request->filled('search'), fn (Builder $query) => $query->search($request->string('search')->toString()))
            ->when($request->filled('category'), fn (Builder $query) => $query->inCategory($request->integer('category')))
            ->when($request->boolean('available'), fn (Builder $query) => $query->available())
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return BookResource::collection($books);
    }

    public function store(StoreBookRequest $request): BookResource
    {
        $book = DB::transaction(function () use ($request): Book {
            $book = Book::create($request->safe()->except('categories'));
            $book->categories()->sync($request->safe()->array('categories'));

            return $book;
        });

        return new BookResource($book->load(['author', 'categories']));
    }

    public function show(Book $book): BookResource
    {
        $this->authorize('view', $book);

        return new BookResource($book->load(['author', 'categories']));
    }

    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        DB::transaction(function () use ($request, $book): void {
            $book->update($request->safe()->except('categories'));

            if ($request->has('categories')) {
                $book->categories()->sync($request->safe()->array('categories'));
            }
        });

        return new BookResource($book->load(['author', 'categories']));
    }

    public function destroy(Book $book): Response
    {
        $this->authorize('delete', $book);

        abort_if($book->hasActiveLoans(), 409, 'This book has active loans and cannot be deleted.');

        $book->delete();

        return response()->noContent();
    }
}
