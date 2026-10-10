<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function asAdmin(): LaravelTestCase
{
    return actingAs(User::factory()->admin()->create());
}

function asMember(): LaravelTestCase
{
    return actingAs(User::factory()->create());
}
