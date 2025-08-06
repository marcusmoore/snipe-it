<?php

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


test('requires permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('users.edit', User::factory()->create()->id))
        ->assertForbidden();
});

test('page renders', function () {
    $this->actingAs(User::factory()->editUsers()->create())
        ->get(route('users.edit', User::factory()->create()->id))
        ->assertOk();
});

test('can view edit page for soft deleted user', function () {
    $user = User::factory()->trashed()->create();

    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('users.edit', $user->id))
        ->assertRedirectToRoute('users.show', $user->id);
});

test('users can be activated with number', function () {
    $admin = User::factory()->editUsers()->create();
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
    $admin = User::factory()->editUsers()->create();
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
    $admin = User::factory()->editUsers()->create();
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
    $admin = User::factory()->editUsers()->create();
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
    $admin = User::factory()->editUsers()->create(['activated' => true]);

    $this->actingAs($admin)
        ->put(route('users.update', $admin), [
            'first_name' => $admin->first_name,
            'username' => $admin->username,
        ]);

    expect($admin->refresh()->activated)->toEqual(1);
});

test('editing users cannot edit escalation fields for admins', function () {
    $admin = User::factory()->editUsers()->create(['activated' => true]);
    $hashed_original = Hash::make('!!094850394680980380kfejlskjfl');
    $hashed_new = Hash::make('!ABCDEFGIJKL123!!!');
    $user = User::factory()->admin()->create(['username' => 'brandnewuser', 'email'=> 'brandnewemail@example.org', 'password' => $hashed_original, 'activated' => true]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'username' => 'brandnewuser',
        'email' => 'brandnewemail@example.org',
        'activated' => 1,
        'password' => $hashed_original,
    ]);

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'username' => 'testnewusername',
            'email' => 'testnewemail@example.org',
            'activated' => 0,
            'password' => 'super-secret',
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'activated' => $user->activated,
        'password' => $hashed_original,
    ]);

    expect($user->refresh()->username)->toEqual('brandnewuser');
    expect($user->refresh()->email)->toEqual('brandnewemail@example.org');
    expect($user->refresh()->activated)->toEqual(1);
    $this->assertNotEquals(Hash::check('super-secret', $user->password), $user->refresh()->password);
    $this->assertNotEquals('testnewusername', $user->refresh()->username);
    $this->assertNotEquals('testnewemail@example.org', $user->refresh()->email);
    $this->assertNotEquals(0, $user->refresh()->activated);
    $this->assertNotEquals(Hash::check('super-secret', $user->password), $user->refresh()->password);
});

test('admin users cannot edit fields for super admins', function () {
    $admin = User::factory()->admin()->create(['activated' => true]);
    $hashed_original = Hash::make('my-awesome-password');
    $user = User::factory()->superuser()->create(['username' => 'brandnewuser', 'email'=> 'brandnewemail@example.org', 'password' => $hashed_original, 'activated' => true]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'username' => 'brandnewuser',
        'email' => 'brandnewemail@example.org',
        'activated' => 1,
        'password' => $hashed_original,
    ]);

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'username' => 'testnewusername',
            'email' => 'testnewemail@example.org',
            'activated' => 0,
            'password' => 'super-secret-new-password',
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'activated' => $user->activated,
        'password' => $hashed_original,
    ]);

    expect($user->refresh()->username)->toEqual('brandnewuser');
    expect($user->refresh()->email)->toEqual('brandnewemail@example.org');
    expect($user->refresh()->activated)->toEqual(1);
    expect(Hash::check('my-awesome-password', $user->password))->toBeTrue($user->refresh()->password);
    $this->assertNotEquals('testnewusername', $user->refresh()->username);
    $this->assertNotEquals('testnewemail@example.org', $user->refresh()->email);
    $this->assertNotTrue(Hash::check('super-secret-new-password', $user->password), $user->refresh()->password);
});

test('multi company user cannot be moved if has asset in different company', function () {
    $this->settings->enableMultipleFullCompanySupport();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $user = User::factory()->create([
        'company_id' => $companyA->id,
    ]);
    $superUser = User::factory()->superuser()->create();

    $asset = Asset::factory()->create([
        'company_id' => $companyA->id,
    ]);

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

test('multi company user can be updated if has asset in same company', function () {
    $this->settings->enableMultipleFullCompanySupport();

    $companyA = Company::factory()->create();

    $user = User::factory()->create([
        'company_id' => $companyA->id,
    ]);
    $superUser = User::factory()->superuser()->create();

    $asset = Asset::factory()->create([
        'company_id' => $companyA->id,
    ]);

    // no assets assigned, therefore success
    $this->actingAs($superUser)->put(route('users.update', $user), [
        'first_name'      => 'test',
        'username'        => 'test',
        'company_id'      => $companyA->id,
        'redirect_option' => 'index'
    ])->assertRedirect(route('users.index'));

    $asset->checkOut($user, $superUser);

    // asset assigned, therefore error
    $response = $this->actingAs($superUser)->patchJson(route('users.update', $user), [
        'first_name'      => 'test',
        'username'        => 'test',
        'company_id'      => $companyA->id,
        'redirect_option' => 'index'
    ]);

    $this->followRedirects($response)->assertSee('success');
});

test('attempting to update deleted user is handled gracefully', function () {
    [$companyA, $companyB] = Company::factory()->count(2)->create();
    $user = User::factory()->for($companyA)->create();
    Asset::factory()->assignedToUser($user)->create();

    $id = $user->id;

    $user->delete();

    $response = $this->actingAs(User::factory()->editUsers()->create())
        ->put(route('users.update', $user), [
            'first_name' => 'test',
            'username' => 'test',
            'company_id' => $companyB->id,
        ]);

    expect($response->exceptions->contains(function ($exception) {
        // Avoid hard 500
        return $exception instanceof Error;
    }))->toBeFalse();

    // As of now, the user will be updated but not be restored
    $this->assertDatabaseHas('users', [
        'id' => $id,
        'first_name' => 'test',
        'username' => 'test',
        'company_id' => $companyB->id,
    ]);
});
