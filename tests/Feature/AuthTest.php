<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

describe('register', function (): void {
    it('creates a member and returns a token', function (): void {
        postJson('/api/register', [
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'maria@example.com')
            ->assertJsonPath('data.role', Role::Member->value)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role'], 'token']);

        assertDatabaseHas('users', ['email' => 'maria@example.com', 'role' => Role::Member->value]);
    });

    it('ignores a role sent in the request', function (): void {
        postJson('/api/register', [
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => Role::Admin->value,
        ])->assertCreated()->assertJsonPath('data.role', Role::Member->value);
    });

    it('validates the payload', function (): void {
        User::factory()->create(['email' => 'taken@example.com']);

        postJson('/api/register', [
            'email' => 'taken@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });
});

describe('login', function (): void {
    it('returns a token with valid credentials', function (): void {
        $user = User::factory()->create();

        postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonStructure(['data', 'token']);
    });

    it('rejects invalid credentials', function (): void {
        $user = User::factory()->create();

        postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
});

describe('protected routes', function (): void {
    it('returns 401 without a token', function (): void {
        getJson('/api/me')->assertUnauthorized();
    });

    it('returns the authenticated user', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    });
});

describe('logout', function (): void {
    it('revokes the current token', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        withToken($token)->postJson('/api/logout')->assertNoContent();

        assertDatabaseCount('personal_access_tokens', 0);
    });
});
