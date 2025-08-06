<?php

use App\Models\Company;
use App\Models\User;

test('permission required to print user inventory', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('users.print', User::factory()->create()))
        ->assertStatus(403);
});

test('can print user inventory', function () {
    $actor = User::factory()->viewUsers()->create();

    $this->actingAs($actor)
        ->get(route('users.print', User::factory()->create()))
        ->assertOk()
        ->assertStatus(200);
});

test('cannot print user inventory from another company', function () {
    $this->settings->enableMultipleFullCompanySupport();

    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $actor = User::factory()->for($companyA)->viewUsers()->create();
    $user = User::factory()->for($companyB)->create();

    $this->actingAs($actor)
        ->get(route('users.print', $user))
        ->assertStatus(302);
});
