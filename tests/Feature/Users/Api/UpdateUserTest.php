<?php

use App\Models\Asset;
use App\Models\Company;
use App\Models\Department;
use App\Models\Group;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('can update user via patch', function () {
    $admin = User::factory()->superuser()->create();
    $manager = User::factory()->create();
    $company = Company::factory()->create();
    $department = Department::factory()->create();
    $location = Location::factory()->create();
    [$groupA, $groupB] = Group::factory()->count(2)->create();

    $user = User::factory()->create([
        'activated' => false,
        'remote' => false,
        'vip' => false,
    ]);

    $this->actingAsForApi($admin)
        ->patchJson(route('api.users.update', $user), [
            'first_name' => 'Mabel',
            'last_name' => 'Mora',
            'username' => 'mabel',
            'password' => 'super-secret',
            'email' => 'mabel@onlymurderspod.com',
            'permissions' => '{"a.new.permission":"1"}',
            'activated' => true,
            'phone' => '619-555-5555',
            'jobtitle' => 'Host',
            'manager_id' => $manager->id,
            'employee_num' => '1111',
            'notes' => 'Pretty good artist',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'location_id' => $location->id,
            'remote' => true,
            'groups' => $groupA->id,
            'vip' => true,
            'start_date' => '2021-08-01',
            'end_date' => '2025-12-31',
        ])
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('success')
        ->json();

    $user->refresh();
    expect($user->first_name)->toEqual('Mabel', 'First name was not updated');
    expect($user->last_name)->toEqual('Mora', 'Last name was not updated');
    expect($user->username)->toEqual('mabel', 'Username was not updated');
    expect(Hash::check('super-secret', $user->password))->toBeTrue('Password was not updated');
    expect($user->email)->toEqual('mabel@onlymurderspod.com', 'Email was not updated');
    expect($user->decodePermissions())->toHaveKey('a.new.permission');
    expect((bool) $user->activated)->toBeTrue('User not marked as activated');
    expect($user->phone)->toEqual('619-555-5555', 'Phone was not updated');
    expect($user->jobtitle)->toEqual('Host', 'Job title was not updated');
    expect($user->manager->is($manager))->toBeTrue('Manager was not updated');
    expect($user->employee_num)->toEqual('1111', 'Employee number was not updated');
    expect($user->notes)->toEqual('Pretty good artist', 'Notes was not updated');
    expect($user->company->is($company))->toBeTrue('Company was not updated');
    expect($user->department->is($department))->toBeTrue('Department was not updated');
    expect($user->location->is($location))->toBeTrue('Location was not updated');
    expect($user->remote)->toEqual(1, 'Remote was not updated');
    expect($user->groups->contains($groupA))->toBeTrue('Groups were not updated');
    expect($user->vip)->toEqual(1, 'VIP was not updated');
    expect($user->start_date)->toEqual('2021-08-01', 'Start date was not updated');
    expect($user->end_date)->toEqual('2025-12-31', 'End date was not updated');

    // `groups` can be an id or array or ids
    $this->patch(route('api.users.update', $user), ['groups' => [$groupA->id, $groupB->id]]);

    $user->refresh();
    expect($user->groups->contains($groupA))->toBeTrue('Not part of expected group');
    expect($user->groups->contains($groupB))->toBeTrue('Not part of expected group');
});

test('can update user via put', function () {
    $admin = User::factory()->superuser()->create();
    $manager = User::factory()->create();
    $company = Company::factory()->create();
    $department = Department::factory()->create();
    $location = Location::factory()->create();
    [$groupA, $groupB] = Group::factory()->count(2)->create();

    $user = User::factory()->create([
        'activated' => false,
        'remote' => false,
        'vip' => false,
    ]);

    $response = $this->actingAsForApi($admin)
        ->putJson(route('api.users.update', $user), [
            'first_name' => 'Mabel',
            'last_name' => 'Mora',
            'username' => 'mabel',
            'password' => 'super-secret',
            'password_confirmation' => 'super-secret',
            'email' => 'mabel@onlymurderspod.com',
            'permissions' => '{"a.new.permission":"1"}',
            'activated' => true,
            'phone' => '619-555-5555',
            'jobtitle' => 'Host',
            'manager_id' => $manager->id,
            'employee_num' => '1111',
            'notes' => 'Pretty good artist',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'location_id' => $location->id,
            'remote' => true,
            'groups' => $groupA->id,
            'vip' => true,
            'start_date' => '2021-08-01',
            'end_date' => '2025-12-31',
        ])
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('success')
        ->json();

    $user->refresh();
    expect($user->first_name)->toEqual('Mabel', 'First name was not updated');
    expect($user->last_name)->toEqual('Mora', 'Last name was not updated');
    expect($user->username)->toEqual('mabel', 'Username was not updated');
    expect(Hash::check('super-secret', $user->password))->toBeTrue('Password was not updated');
    expect($user->email)->toEqual('mabel@onlymurderspod.com', 'Email was not updated');
    expect($user->decodePermissions())->toHaveKey('a.new.permission');
    expect((bool) $user->activated)->toBeTrue('User not marked as activated');
    expect($user->phone)->toEqual('619-555-5555', 'Phone was not updated');
    expect($user->jobtitle)->toEqual('Host', 'Job title was not updated');
    expect($user->manager->is($manager))->toBeTrue('Manager was not updated');
    expect($user->employee_num)->toEqual('1111', 'Employee number was not updated');
    expect($user->notes)->toEqual('Pretty good artist', 'Notes was not updated');
    expect($user->company->is($company))->toBeTrue('Company was not updated');
    expect($user->department->is($department))->toBeTrue('Department was not updated');
    expect($user->location->is($location))->toBeTrue('Location was not updated');
    expect($user->remote)->toEqual(1, 'Remote was not updated');
    expect($user->groups->contains($groupA))->toBeTrue('Groups were not updated');
    expect($user->vip)->toEqual(1, 'VIP was not updated');
    expect($user->start_date)->toEqual('2021-08-01', 'Start date was not updated');
    expect($user->end_date)->toEqual('2025-12-31', 'End date was not updated');

    // `groups` can be an id or array or ids
    $this->patch(route('api.users.update', $user), ['groups' => [$groupA->id, $groupB->id]]);

    $user->refresh();
    expect($user->groups->contains($groupA))->toBeTrue('Not part of expected group');
    expect($user->groups->contains($groupB))->toBeTrue('Not part of expected group');
});

test('api users can be activated with number', function () {
    $admin = User::factory()->editUsers()->create();
    $user = User::factory()->create(['activated' => 0]);

    $this->actingAsForApi($admin)
        ->patch(route('api.users.update', $user), [
            'activated' => 1,
        ]);

    expect($user->refresh()->activated)->toEqual(1);
});

test('api users can be activated with boolean true', function () {
    $admin = User::factory()->editUsers()->create();
    $user = User::factory()->create(['activated' => false]);

    $this->actingAsForApi($admin)
        ->patch(route('api.users.update', $user), [
            'activated' => true,
        ]);

    expect($user->refresh()->activated)->toEqual(1);
});

test('api users can be deactivated with number', function () {
    $admin = User::factory()->editUsers()->create();
    $user = User::factory()->create(['activated' => true]);

    $this->actingAsForApi($admin)
        ->patch(route('api.users.update', $user), [
            'activated' => 0,
        ]);

    expect($user->refresh()->activated)->toEqual(0);
});

test('api users can be deactivated with boolean false', function () {
    $admin = User::factory()->editUsers()->create();
    $user = User::factory()->create(['activated' => true]);

    $this->actingAsForApi($admin)
        ->patch(route('api.users.update', $user), [
            'activated' => false,
        ]);

    expect($user->refresh()->activated)->toEqual(0);
});

test('editing users cannot edit escalation fields for admins', function () {
    $hashed_original = Hash::make('!!094850394680980380kfejlskjfl');
    $hashed_new = Hash::make('!ABCDEFGIJKL123!!!');
    $admin = User::factory()->editUsers()->create();
    $user = User::factory()->admin()->create(['username' => 'brandnewuser', 'email' => 'brandnewemail@example.org', 'password' => $hashed_original, 'activated' => 1]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'username' => 'brandnewuser',
        'email' => 'brandnewemail@example.org',
        'activated' => 1,
        'password' => $hashed_original,
    ]);

    $this->actingAsForApi($admin)
        ->patch(route('api.users.update', $user), [
            'username' => 'testnewusername',
            'email' => 'testnewemail@example.org',
            'activated' => 0,
            'password' => $hashed_new,
        ]);

    expect($user->refresh()->activated)->toEqual(0);
});

test('users scoped to company during update when multiple full company support enabled', function () {
    $this->settings->enableMultipleFullCompanySupport();

    $companyA = Company::factory()->create(['name' => 'Company A']);
    $companyB = Company::factory()->create(['name' => 'Company B']);

    $adminA = User::factory(['company_id' => $companyA->id])->admin()->create();
    $adminB = User::factory(['company_id' => $companyB->id])->admin()->create();
    $adminNoCompany = User::factory(['company_id' => null])->admin()->create();

    // Create users that belongs to company A and B and one that is unscoped
    $scoped_user_in_companyA = User::factory()->create(['company_id' => $companyA->id]);
    $scoped_user_in_companyB = User::factory()->create(['company_id' => $companyB->id]);
    $scoped_user_in_no_company = User::factory()->create(['company_id' => null]);

    // Admin for Company A should allow updating user from Company A
    $this->actingAsForApi($adminA)
        ->patchJson(route('api.users.update', $scoped_user_in_companyA))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('success')
        ->json();

    // Admin for Company A should get denied updating user from Company B
    $this->actingAsForApi($adminA)
        ->patchJson(route('api.users.update', $scoped_user_in_companyB))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();

    // Admin for Company A should get denied updating user without a company
    $this->actingAsForApi($adminA)
        ->patchJson(route('api.users.update', $scoped_user_in_no_company))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();

    // Admin for Company B should allow updating user from Company B
    $this->actingAsForApi($adminB)
        ->patchJson(route('api.users.update', $scoped_user_in_companyB))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('success')
        ->json();

    // Admin for Company B should get denied updating user from Company A
    $this->actingAsForApi($adminB)
        ->patchJson(route('api.users.update', $scoped_user_in_companyA))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();

    // Admin for Company B should get denied updating user without a company
    $this->actingAsForApi($adminB)
        ->patchJson(route('api.users.update', $scoped_user_in_no_company))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();

    // Admin without a company should allow updating user without a company
    $this->actingAsForApi($adminNoCompany)
        ->patchJson(route('api.users.update', $scoped_user_in_no_company))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('success')
        ->json();

    // Admin without a company should get denied updating user from Company A
    $this->actingAsForApi($adminNoCompany)
        ->patchJson(route('api.users.update', $scoped_user_in_companyA))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();

    // Admin without a company should get denied updating user from Company B
    $this->actingAsForApi($adminNoCompany)
        ->patchJson(route('api.users.update', $scoped_user_in_companyB))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('user groups are only updated if authenticated user is super user', function () {
    $groupToJoin = Group::factory()->create();

    $userWhoCanEditUsers = User::factory()->editUsers()->create();
    $superUser = User::factory()->superuser()->create();

    $userToUpdateByUserWhoCanEditUsers = User::factory()->create();
    $userToUpdateByToUserBySuperuser = User::factory()->create();

    $this->actingAsForApi($userWhoCanEditUsers)
        ->patchJson(route('api.users.update', $userToUpdateByUserWhoCanEditUsers), [
            'groups' => [$groupToJoin->id],
        ]);

    $this->actingAsForApi($superUser)
        ->patchJson(route('api.users.update', $userToUpdateByToUserBySuperuser), [
            'groups' => [$groupToJoin->id],
        ]);

    expect($userToUpdateByUserWhoCanEditUsers->refresh()->groups->contains($groupToJoin))->toBeFalse('Non-super-user was able to modify user group');

    expect($userToUpdateByToUserBySuperuser->refresh()->groups->contains($groupToJoin))->toBeTrue();
});

test('user groups can be cleared by super user', function () {
    $normalUser = User::factory()->editUsers()->create();
    $superUser = User::factory()->superuser()->create();

    $oneUserToUpdate = User::factory()->create();
    $anotherUserToUpdate = User::factory()->create();

    $joinedGroup = Group::factory()->create();
    $oneUserToUpdate->groups()->sync([$joinedGroup->id]);
    $anotherUserToUpdate->groups()->sync([$joinedGroup->id]);

    $this->actingAsForApi($normalUser)
        ->patchJson(route('api.users.update', $oneUserToUpdate), [
            'groups' => null,
        ]);

    $this->actingAsForApi($superUser)
        ->patchJson(route('api.users.update', $anotherUserToUpdate), [
            'groups' => null,
        ]);

    expect($oneUserToUpdate->refresh()->groups->contains($joinedGroup))->toBeTrue();
    expect($anotherUserToUpdate->refresh()->groups->contains($joinedGroup))->toBeFalse();
});

test('non superuser cannot update own groups', function () {
    $groupToJoin = Group::factory()->create();
    $user = User::factory()->editUsers()->create();

    $this->actingAsForApi($user)
        ->patchJson(route('api.users.update', $user), [
            'groups' => [$groupToJoin->id],
        ]);

    expect($user->refresh()->groups->contains($groupToJoin))->toBeFalse('Non-super-user was able to modify user group');
});

test('non superuser cannot update groups', function () {
    $user = User::factory()->editUsers()->create();
    $group = Group::factory()->create();
    $user->groups()->sync([$group->id]);
    $newGroupToJoin = Group::factory()->create();

    $this->actingAsForApi($user)
        ->patchJson(route('api.users.update', $user), [
            'groups' => [$newGroupToJoin->id],
        ]);

    expect($user->refresh()->groups->contains($newGroupToJoin))->toBeFalse('Non-super-user was able to modify user group membership');

    expect($user->refresh()->groups->contains($group))->toBeTrue();
});

test('users groups are not cleared if no group passed by super user', function () {
    $user = User::factory()->create();
    $superUser = User::factory()->superuser()->create();

    $group = Group::factory()->create();
    $user->groups()->sync([$group->id]);

    $this->actingAsForApi($superUser)
        ->patchJson(route('api.users.update', $user), []);

    expect($user->refresh()->groups->contains($group))->toBeTrue();
});

test('multiple groups update by super user', function () {
    $user = User::factory()->create();
    $superUser = User::factory()->superuser()->create();

    $groupA = Group::factory()->create(['name' => 'Group A']);
    $groupB = Group::factory()->create(['name' => 'Group B']);

    $this->actingAsForApi($superUser)
        ->patchJson(route('api.users.update', $user), [
            'groups' => [$groupA->id, $groupB->id],
        ])->json();

    expect($user->refresh()->groups->contains($groupA))->toBeTrue();
    expect($user->refresh()->groups->contains($groupB))->toBeTrue();
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
    $this->actingAsForApi($superUser)->patchJson(route('api.users.update', $user), [
        'username' => 'test',
        'company_id' => $companyB->id,
    ])->assertStatusMessageIs('success');

    // same test but PUT
    $this->actingAsForApi($superUser)->putJson(route('api.users.update', $user), [
        'username' => 'test',
        'first_name' => 'Test',
        'company_id' => $companyB->id,
    ])->assertStatusMessageIs('success');

    $asset->checkOut($user, $superUser);

    // asset assigned, therefore error
    $this->actingAsForApi($superUser)->patchJson(route('api.users.update', $user), [
        'username' => 'test',
        'company_id' => $companyB->id,
    ])->assertStatusMessageIs('error');

    // same test but PUT
    $this->actingAsForApi($superUser)->putJson(route('api.users.update', $user), [
        'username' => 'test',
        'first_name' => 'Test',
        'company_id' => $companyB->id,
    ])->assertStatusMessageIs('error');
});

test('multi company user can be updated if has asset in same company', function () {
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

    // no assets assigned from other company, therefore success
    $this->actingAsForApi($superUser)->patchJson(route('api.users.update', $user), [
        'username' => 'test',
        'company_id' => $companyB->id,
    ])->assertStatusMessageIs('success');

    // same test but PUT
    $this->actingAsForApi($superUser)->putJson(route('api.users.update', $user), [
        'username' => 'test',
        'first_name' => 'Test',
        'company_id' => $companyB->id,
    ])->assertStatusMessageIs('success');

    $asset->checkOut($user, $superUser);

    // asset assigned from other company, therefore error
    $this->actingAsForApi($superUser)->patchJson(route('api.users.update', $user), [
        'username' => 'test',
        'company_id' => $companyB->id,
    ])->assertStatusMessageIs('error');

    // same test but PUT
    $this->actingAsForApi($superUser)->putJson(route('api.users.update', $user), [
        'username' => 'test',
        'first_name' => 'Test',
        'company_id' => $companyB->id,
    ])->assertStatusMessageIs('error');
});
