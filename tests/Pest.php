<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

function asAdmin(): LaravelTestCase
{
    return actingAs(User::factory()->admin()->create());
}

function asMember(): LaravelTestCase
{
    return actingAs(User::factory()->create());
}

/**
 * @param  array<string, mixed>  $parameters
 */
function command(string $command, array $parameters = []): PendingCommand
{
    $pending = artisan($command, $parameters);

    if (! $pending instanceof PendingCommand) {
        throw new LogicException('Console output mocking must be enabled to assert on commands.');
    }

    return $pending;
}
