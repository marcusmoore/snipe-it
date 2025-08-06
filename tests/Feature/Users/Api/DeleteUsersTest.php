<?php

use App\Models\Company;
use App\Models\LicenseSeat;
use App\Models\Location;
use App\Models\User;

test('requires permission', function () {
    $user = User::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->deleteJson(route('api.users.destroy', $user))
        ->assertForbidden();

    $this->assertNotSoftDeleted($user);
});

test('error returned via api if user does not exist', function () {
    $this->actingAsForApi(User::factory()->deleteUsers()->create())
        ->deleteJson(route('api.users.destroy', 'invalid-id'))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('error returned via api if user is already deleted', function () {
    $user = User::factory()->deletedUser()->create();
    $this->actingAsForApi(User::factory()->deleteUsers()->create())
        ->deleteJson(route('api.users.destroy', $user))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('disallow user deletion via api if still managing people', function () {
    $manager = User::factory()->create();
    User::factory()->count(5)->create(['manager_id' => $manager->id]);
    expect($manager->isDeletable())->toBeFalse();

    $this->actingAsForApi(User::factory()->deleteUsers()->create())
        ->deleteJson(route('api.users.destroy', $manager))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('disallow user deletion via api if still managing locations', function () {
    $manager = User::factory()->create();
    Location::factory()->count(5)->create(['manager_id' => $manager->id]);

    expect($manager->isDeletable())->toBeFalse();

    $this->actingAsForApi(User::factory()->deleteUsers()->create())
        ->deleteJson(route('api.users.destroy', $manager))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('disallow user deletion via api if still has licenses', function () {
    $manager = User::factory()->create();
    LicenseSeat::factory()->count(5)->create(['assigned_to' => $manager->id]);

    expect($manager->isDeletable())->toBeFalse();

    $this->actingAsForApi(User::factory()->deleteUsers()->create())
        ->deleteJson(route('api.users.destroy', $manager))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('users cannot delete themselves', function () {
    $user = User::factory()->deleteUsers()->create();
    $this->actingAsForApi($user)
        ->deleteJson(route('api.users.destroy', $user))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('adheres to full multiple companies support scoping', function () {
    $this->settings->enableMultipleFullCompanySupport();

    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $superuser = User::factory()->superuser()->create();
    $userFromA = User::factory()->deleteUsers()->for($companyA)->create();
    $userFromB = User::factory()->deleteUsers()->for($companyB)->create();

    $this->actingAsForApi($userFromA)
        ->deleteJson(route('api.users.destroy', $userFromB))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();

    $userFromB->refresh();
    expect($userFromB->deleted_at)->toBeNull();

    $this->actingAsForApi($userFromB)
        ->deleteJson(route('api.users.destroy', $userFromA))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();

    $userFromA->refresh();
    expect($userFromA->deleted_at)->toBeNull();

    $this->actingAsForApi($superuser)
        ->deleteJson(route('api.users.destroy', $userFromA))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('success')
        ->json();

    $userFromA->refresh();
    expect($userFromA->deleted_at)->not->toBeNull();
});

test('can delete user', function () {
    $user = User::factory()->create();

    $this->actingAsForApi(User::factory()->deleteUsers()->create())
        ->deleteJson(route('api.users.destroy', $user))
        ->assertOk()
        ->assertStatusMessageIs('success');

    $this->assertSoftDeleted($user);
});
