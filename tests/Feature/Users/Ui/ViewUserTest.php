<?php

use App\Models\Company;
use App\Models\User;
use App\Notifications\CurrentInventory;
use Illuminate\Support\Facades\Notification;

test('permissions for user detail page', function () {
    $this->settings->enableMultipleFullCompanySupport();

    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $superuser = User::factory()->superuser()->create();
    $user = User::factory()->for($companyB)->create();

    $this->actingAs(User::factory()->editUsers()->for($companyA)->create())
        ->get(route('users.show', ['user' => $user->id]))
        ->assertStatus(403);

    $this->actingAs($superuser)
        ->get(route('users.show', ['user' => $user->id]))
        ->assertOk()
        ->assertStatus(200);
});

test('permissions for print all inventory page', function () {
    $this->settings->enableMultipleFullCompanySupport();

    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $superuser = User::factory()->superuser()->create();
    $user = User::factory()->for($companyB)->create();

    $this->actingAs(User::factory()->viewUsers()->for($companyA)->create())
        ->get(route('users.print', ['userId' => $user->id]))
        ->assertStatus(302);

    $this->actingAs(User::factory()->viewUsers()->for($companyB)->create())
        ->get(route('users.print', ['userId' => $user->id]))
        ->assertStatus(200);

    $this->actingAs($superuser)
        ->get(route('users.print', ['userId' => $user->id]))
        ->assertOk()
        ->assertStatus(200);
});

test('user without company permissions cannot send inventory', function () {
    Notification::fake();

    $this->settings->enableMultipleFullCompanySupport();

    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $superuser = User::factory()->superuser()->create();
    $user = User::factory()->for($companyB)->create();

    $this->actingAs(User::factory()->viewUsers()->for($companyA)->create())
        ->post(route('users.email', ['userId' => $user->id]))
        ->assertStatus(403);

    $this->actingAs(User::factory()->viewUsers()->for($companyB)->create())
        ->post(route('users.email', ['userId' => $user->id]))
        ->assertStatus(302);

    $this->actingAs($superuser)
        ->post(route('users.email', ['userId' => $user->id]))
        ->assertStatus(302);

    Notification::assertSentTo(
        [$user], CurrentInventory::class
    );
});
