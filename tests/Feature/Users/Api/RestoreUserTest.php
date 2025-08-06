<?php

use App\Models\Company;
use App\Models\User;

test('error returned via api if user does not exist', function () {
    $this->actingAsForApi(User::factory()->deleteUsers()->create())
        ->postJson(route('api.users.restore', 'invalid-id'))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('error returned via api if user is not deleted', function () {
    $user = User::factory()->create();
    $this->actingAsForApi(User::factory()->deleteUsers()->create())
        ->postJson(route('api.users.restore', $user->id))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('denied permissions for restoring user via api', function () {
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.users.restore', User::factory()->deletedUser()->create()))
        ->assertStatus(403)
        ->json();
});

test('success permissions for restoring user via api', function () {
    $deleted_user = User::factory()->deletedUser()->create();

    $this->actingAsForApi(User::factory()->admin()->create())
        ->postJson(route('api.users.restore', ['user' => $deleted_user]))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('success')
        ->json();

    $deleted_user->refresh();
    expect($deleted_user->deleted_at)->toBeNull();
});

test('permissions for restoring if not in same company and not superadmin', function () {
    $this->settings->enableMultipleFullCompanySupport();

    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $superuser = User::factory()->superuser()->create();
    $userFromA = User::factory()->deletedUser()->deleteUsers()->for($companyA)->create();
    $userFromB = User::factory()->deletedUser()->deleteUsers()->for($companyB)->create();

    $this->actingAsForApi($userFromA)
        ->postJson(route('api.users.restore', ['user' => $userFromB->id]))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();

    $userFromB->refresh();
    expect($userFromB->deleted_at)->not->toBeNull();

    $this->actingAsForApi($userFromB)
        ->postJson(route('api.users.restore', ['user' => $userFromA->id]))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();

    $userFromA->refresh();
    expect($userFromA->deleted_at)->not->toBeNull();

    $this->actingAsForApi($superuser)
        ->postJson(route('api.users.restore', ['user' => $userFromA->id]))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('success')
        ->json();

    $userFromA->refresh();
    expect($userFromA->deleted_at)->toBeNull();
});
