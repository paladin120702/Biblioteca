<?php

declare(strict_types=1);

use App\Models\Author;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\getJson;

it('requires authentication', function (): void {
    getJson('/api/authors')->assertUnauthorized();
});

it('lets any authenticated user list and view authors', function (): void {
    $author = Author::factory()->create();

    asMember()->getJson('/api/authors')->assertOk()->assertJsonPath('data.0.id', $author->id);
    asMember()->getJson("/api/authors/{$author->id}")->assertOk()->assertJsonPath('data.name', $author->name);
});

it('lets an admin create, update and delete authors', function (): void {
    asAdmin()->postJson('/api/authors', ['name' => 'Machado de Assis'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Machado de Assis');

    $id = Author::sole()->id;

    asAdmin()->putJson("/api/authors/{$id}", ['name' => 'Clarice Lispector'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Clarice Lispector');

    asAdmin()->deleteJson("/api/authors/{$id}")->assertNoContent();

    assertDatabaseMissing('authors', ['id' => $id]);
});

it('forbids members from writing authors', function (): void {
    $author = Author::factory()->create();

    asMember()->postJson('/api/authors', ['name' => 'Someone'])->assertForbidden();
    asMember()->putJson("/api/authors/{$author->id}", ['name' => 'Someone'])->assertForbidden();
    asMember()->deleteJson("/api/authors/{$author->id}")->assertForbidden();

    assertDatabaseHas('authors', ['id' => $author->id, 'name' => $author->name]);
});

it('validates the author payload', function (): void {
    asAdmin()->postJson('/api/authors', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});
