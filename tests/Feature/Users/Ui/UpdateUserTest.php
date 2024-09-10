<?php

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;

test('users can be activated with number', function () {
    $admin = User::factory()->superuser()->create();
    $user = User::factory()->create(['activated' => 0]);

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'first_name' => $user->first_name,
            'username' => $user->username,
            'activated' => 1,
        ]);

    expect($user->refresh()->activated)->toEqual(1);
});

test('users can be activated with boolean true', function () {
    $admin = User::factory()->superuser()->create();
    $user = User::factory()->create(['activated' => false]);

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'first_name' => $user->first_name,
            'username' => $user->username,
            'activated' => true,
        ]);

    expect($user->refresh()->activated)->toEqual(1);
});

test('users can be deactivated with number', function () {
    $admin = User::factory()->superuser()->create();
    $user = User::factory()->create(['activated' => true]);

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'first_name' => $user->first_name,
            'username' => $user->username,
            'activated' => 0,
        ]);

    expect($user->refresh()->activated)->toEqual(0);
});

test('users can be deactivated with boolean false', function () {
    $admin = User::factory()->superuser()->create();
    $user = User::factory()->create(['activated' => true]);

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'first_name' => $user->first_name,
            'username' => $user->username,
            'activated' => false,
        ]);

    expect($user->refresh()->activated)->toEqual(0);
});

test('users updating themselves do not deactivate their account', function () {
    $admin = User::factory()->superuser()->create(['activated' => true]);

    $this->actingAs($admin)
        ->put(route('users.update', $admin), [
            'first_name' => $admin->first_name,
            'username' => $admin->username,
        ]);

    expect($admin->refresh()->activated)->toEqual(1);
});

test('multi company user cannot be moved if has asset', function () {
    $this->settings->enableMultipleFullCompanySupport();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $user = User::factory()->create([
        'company_id' => $companyA->id,
    ]);
    $superUser = User::factory()->superuser()->create();

    $asset = Asset::factory()->create();

    // no assets assigned, therefore success
    $this->actingAs($superUser)->put(route('users.update', $user), [
        'first_name'      => 'test',
        'username'        => 'test',
        'company_id'      => $companyB->id,
        'redirect_option' => 'index'
    ])->assertRedirect(route('users.index'));

    $asset->checkOut($user, $superUser);

    // asset assigned, therefore error
    $response = $this->actingAs($superUser)->patchJson(route('users.update', $user), [
        'first_name'      => 'test',
        'username'        => 'test',
        'company_id'      => $companyB->id,
        'redirect_option' => 'index'
    ]);

    $this->followRedirects($response)->assertSee('error');
});
