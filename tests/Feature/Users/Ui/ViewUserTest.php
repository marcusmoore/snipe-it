<?php

use App\Models\Company;
use App\Models\User;

test('requires permission to view user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('users.show', User::factory()->create()))
        ->assertStatus(403);
});

test('can view user', function () {
    $actor = User::factory()->viewUsers()->create();

    $this->actingAs($actor)
        ->get(route('users.show', User::factory()->create()))
        ->assertOk()
        ->assertStatus(200);
});

test('cannot view user from another company', function () {
    $this->settings->enableMultipleFullCompanySupport();

    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $actor = User::factory()->for($companyA)->viewUsers()->create();
    $user = User::factory()->for($companyB)->create();

    $this->actingAs($actor)
        ->get(route('users.show', $user))
        ->assertStatus(302);
});
