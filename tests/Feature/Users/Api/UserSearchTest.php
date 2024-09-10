<?php

use App\Models\Company;
use App\Models\User;
use Laravel\Passport\Passport;

test('can search by user first and last name', function () {
    User::factory()->create(['first_name' => 'Luke', 'last_name' => 'Skywalker']);
    User::factory()->create(['first_name' => 'Darth', 'last_name' => 'Vader']);

    Passport::actingAs(User::factory()->viewUsers()->create());
    $response = $this->getJson(route('api.users.index', ['search' => 'luke sky']))->assertOk();

    $results = collect($response->json('rows'));

    expect($results->count())->toEqual(1);
    expect($results->pluck('name')->contains(fn($text) => str_contains($text, 'Luke')))->toBeTrue();
    expect($results->pluck('name')->contains(fn($text) => str_contains($text, 'Darth')))->toBeFalse();
});

test('results when searching for active users', function () {
    User::factory()->create(['first_name' => 'Active', 'last_name' => 'User']);
    User::factory()->create(['first_name' => 'Deleted', 'last_name' => 'User'])->delete();

    $response = $this->actingAsForApi(User::factory()->viewUsers()->create())
        ->getJson(route('api.users.index', [
            'deleted' => 'false',
            'company_id' => '',
            'search' => 'user',
            'order' => 'asc',
            'offset' => '0',
            'limit' => '20',
        ]))
        ->assertOk();

    $firstNames = collect($response->json('rows'))->pluck('first_name');

    expect($firstNames->contains('Active'))->toBeTrue('Expected user does not appear in results');

    expect($firstNames->contains('Deleted'))->toBeFalse('Unexpected deleted user appears in results');
});

test('results when searching for deleted users', function () {
    User::factory()->create(['first_name' => 'Active', 'last_name' => 'User']);
    User::factory()->create(['first_name' => 'Deleted', 'last_name' => 'User'])->delete();

    $response = $this->actingAsForApi(User::factory()->viewUsers()->create())
        ->getJson(route('api.users.index', [
            'deleted' => 'true',
            'company_id' => '',
            'search' => 'user',
            'order' => 'asc',
            'offset' => '0',
            'limit' => '20',
        ]))
        ->assertOk();

    $firstNames = collect($response->json('rows'))->pluck('first_name');

    expect($firstNames->contains('Active'))->toBeFalse('Unexpected active user appears in results');

    expect($firstNames->contains('Deleted'))->toBeTrue('Expected deleted user does not appear in results');
});

test('users scoped to company when multiple full company support enabled', function () {
    $this->settings->enableMultipleFullCompanySupport();

    $companyA = Company::factory()
        ->has(User::factory(['first_name' => 'Company A', 'last_name' => 'User']))
        ->create();

    Company::factory()
        ->has(User::factory(['first_name' => 'Company B', 'last_name' => 'User']))
        ->create();

    $response = $this->actingAsForApi(User::factory()->for($companyA)->viewUsers()->create())
        ->getJson(route('api.users.index'))
        ->assertOk();

    $results = collect($response->json('rows'));

    expect($results->pluck('name')->contains(fn($text) => str_contains($text, 'Company A')))->toBeTrue('User index does not contain expected user');
    expect($results->pluck('name')->contains(fn($text) => str_contains($text, 'Company B')))->toBeFalse('User index contains unexpected user from another company');
});

test('users scoped to company during search when multiple full company support enabled', function () {
    $this->settings->enableMultipleFullCompanySupport();

    $companyA = Company::factory()
        ->has(User::factory(['first_name' => 'Company A', 'last_name' => 'User']))
        ->create();

    Company::factory()
        ->has(User::factory(['first_name' => 'Company B', 'last_name' => 'User']))
        ->create();

    $response = $this->actingAsForApi(User::factory()->for($companyA)->viewUsers()->create())
        ->getJson(route('api.users.index', [
            'deleted' => 'false',
            'company_id' => null,
            'search' => 'user',
            'order' => 'asc',
            'offset' => '0',
            'limit' => '20',
        ]))
        ->assertOk();

    $results = collect($response->json('rows'));

    expect($results->pluck('name')->contains(fn($text) => str_contains($text, 'Company A')))->toBeTrue('User index does not contain expected user');
    expect($results->pluck('name')->contains(fn($text) => str_contains($text, 'Company B')))->toBeFalse('User index contains unexpected user from another company');
});

test('users index when invalid sort field is passed', function () {
    $this->markIncompleteIfSqlite('This test is not compatible with SQLite');

    $this->actingAsForApi(User::factory()->viewUsers()->create())
        ->getJson(route('api.users.index', [
            'sort' => 'assets',
        ]))
        ->assertOk()
        ->assertStatus(200)
        ->json();
});
