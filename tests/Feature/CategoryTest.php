<?php

declare(strict_types=1);

use App\Models\Category;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\getJson;

it('requires authentication', function (): void {
    getJson('/api/categories')->assertUnauthorized();
});

it('lets any authenticated user list and view categories', function (): void {
    $category = Category::factory()->create();

    asMember()->getJson('/api/categories')->assertOk()->assertJsonPath('data.0.id', $category->id);
    asMember()->getJson("/api/categories/{$category->id}")->assertOk()->assertJsonPath('data.name', $category->name);
});

it('lets an admin create, update and delete categories', function (): void {
    asAdmin()->postJson('/api/categories', ['name' => 'Romance'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Romance');

    $id = Category::sole()->id;

    asAdmin()->putJson("/api/categories/{$id}", ['name' => 'Ficção'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Ficção');

    asAdmin()->deleteJson("/api/categories/{$id}")->assertNoContent();

    assertDatabaseMissing('categories', ['id' => $id]);
});

it('forbids members from writing categories', function (): void {
    $category = Category::factory()->create();

    asMember()->postJson('/api/categories', ['name' => 'Poesia'])->assertForbidden();
    asMember()->putJson("/api/categories/{$category->id}", ['name' => 'Poesia'])->assertForbidden();
    asMember()->deleteJson("/api/categories/{$category->id}")->assertForbidden();

    assertDatabaseHas('categories', ['id' => $category->id, 'name' => $category->name]);
});

it('requires a unique category name', function (): void {
    Category::factory()->create(['name' => 'Romance']);

    asAdmin()->postJson('/api/categories', ['name' => 'Romance'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('keeps the name valid when updating a category with its own name', function (): void {
    $category = Category::factory()->create(['name' => 'Romance']);

    asAdmin()->putJson("/api/categories/{$category->id}", ['name' => 'Romance'])->assertOk();
});
