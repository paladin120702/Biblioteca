<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Author\StoreAuthorRequest;
use App\Http\Requests\Author\UpdateAuthorRequest;
use App\Http\Resources\AuthorResource;
use App\Models\Author;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AuthorController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Author::class);

        return AuthorResource::collection(Author::orderBy('name')->paginate(15));
    }

    public function store(StoreAuthorRequest $request): AuthorResource
    {
        return new AuthorResource(Author::create($request->validated()));
    }

    public function show(Author $author): AuthorResource
    {
        $this->authorize('view', $author);

        return new AuthorResource($author);
    }

    public function update(UpdateAuthorRequest $request, Author $author): AuthorResource
    {
        $author->update($request->validated());

        return new AuthorResource($author);
    }

    public function destroy(Author $author): Response
    {
        $this->authorize('delete', $author);

        abort_if($author->hasBooks(), 409, 'This author has books and cannot be deleted.');

        $author->delete();

        return response()->noContent();
    }
}
