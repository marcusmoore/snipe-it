<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Group;
use App\Models\Location;
use App\Models\User;

describe('permission checks', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
    });

    test('permission required to view create page', function () {
        $this->get(route('users.create'))
            ->assertForbidden();
    });

    test('permission required to create user', function () {
        $this->post(route('users.store'), [
            'first_name' => 'Suki',
            'username' => 'suki',
            'password' => 'super-secret',
            'password_confirmation' => 'super-secret',
        ])
            ->assertForbidden();
    });
});

test('create page renders', function () {
    $admin = User::factory()->createUsers()->create();

    $this->actingAs(User::factory()->createUsers()->create())
        ->get(route('users.create'))
        ->assertOk()
        ->assertDontSee($admin->first_name)
        ->assertDontSee($admin->last_name)
        ->assertDontSee($admin->email);
});

test('can create user', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    $department = Department::factory()->create();
    $location = Location::factory()->create();
    [$groupA, $groupB, $groupC] = Group::factory()->count(3)->create();

    $this->actingAs(User::factory()->createUsers()->create())
        ->post(route('users.store'), [
            'first_name' => 'Suki',
            'last_name' => 'Waterhouse',
            'username' => 'sukiwaterhouse',
            'password' => 'super-secret',
            'password_confirmation' => 'super-secret',
            'activated' => '1',
            'email' => 'suki@example.com',
            'email_user' => '1',
            'company_id' => $company->id,
            'locale' => 'en-US',
            'employee_num' => '1234',
            'jobtitle' => 'Manager',
            'manager_id' => $manager->id,
            'department_id' => $department->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-05-25',
            'vip' => '1',
            'autoassign_licenses' => '1',
            'remote' => '1',
            'location_id' => $location->id,
            'phone' => '555-5555',
            'website' => 'https://www.example.com',
            'address' => '23456 Main St',
            'city' => 'Waterside',
            'state' => 'CA',
            'country' => 'AE',
            'zip' => '12345',
            'notes' => 'some notes',
            'groups' => [
                $groupA->id,
                $groupB->id,
            ],
            // @todo:
            'permission' => [
                'superuser' => '0',
                'admin' => '-1',
                'import' => '0',
                'reports.view' => '0',
                'assets.view' => '1',
                'assets.create' => '1',
                'assets.edit' => '1',
                'assets.delete' => '1',
                'assets.checkin' => '1',
                'assets.checkout' => '1',
                'assets.audit' => '1',
                'assets.view.requestable' => '1',
                'assets.view.encrypted_custom_fields' => '1',
                'accessories.view' => '0',
                'accessories.create' => '0',
                'accessories.edit' => '0',
                'accessories.delete' => '0',
                'accessories.checkout' => '0',
                'accessories.checkin' => '0',
                'accessories.files' => '0',
                'consumables.view' => '0',
                'consumables.create' => '0',
                'consumables.edit' => '0',
                'consumables.delete' => '0',
                'consumables.checkout' => '0',
                'consumables.files' => '0',
                'licenses.view' => '0',
                'licenses.create' => '0',
                'licenses.edit' => '0',
                'licenses.delete' => '0',
                'licenses.checkout' => '0',
                'licenses.keys' => '0',
                'licenses.files' => '0',
                'components.view' => '0',
                'components.create' => '0',
                'components.edit' => '0',
                'components.delete' => '0',
                'components.checkout' => '0',
                'components.checkin' => '0',
                'components.files' => '0',
                'kits.view' => '0',
                'kits.create' => '0',
                'kits.edit' => '0',
                'kits.delete' => '0',
                'users.view' => '0',
                'users.create' => '0',
                'users.edit' => '0',
                'users.delete' => '0',
                'models.view' => '0',
                'models.create' => '0',
                'models.edit' => '0',
                'models.delete' => '0',
                'categories.view' => '0',
                'categories.create' => '0',
                'categories.edit' => '0',
                'categories.delete' => '0',
                'departments.view' => '0',
                'departments.create' => '0',
                'departments.edit' => '0',
                'departments.delete' => '0',
                'statuslabels.view' => '0',
                'statuslabels.create' => '0',
                'statuslabels.edit' => '0',
                'statuslabels.delete' => '0',
                'customfields.view' => '0',
                'customfields.create' => '0',
                'customfields.edit' => '0',
                'customfields.delete' => '0',
                'suppliers.view' => '0',
                'suppliers.create' => '0',
                'suppliers.edit' => '0',
                'suppliers.delete' => '0',
                'manufacturers.view' => '0',
                'manufacturers.create' => '0',
                'manufacturers.edit' => '0',
                'manufacturers.delete' => '0',
                'depreciations.view' => '0',
                'depreciations.create' => '0',
                'depreciations.edit' => '0',
                'depreciations.delete' => '0',
                'locations.view' => '0',
                'locations.create' => '0',
                'locations.edit' => '0',
                'locations.delete' => '0',
                'companies.view' => '0',
                'companies.create' => '0',
                'companies.edit' => '0',
                'companies.delete' => '0',
                'self.two_factor' => '0',
                'self.api' => '0',
                'self.edit_location' => '0',
                'self.checkout_assets' => '0',
                'self.view_purchase_cost' => '0',
            ],
            // @todo:
            'Assets' => '1',
            'redirect_option' => 'index',
        ])->assertRedirectToRoute('users.index');

    $this->assertDatabaseHas('users', [
        'first_name' => 'Suki',
        'last_name' => 'Waterhouse',
        'username' => 'sukiwaterhouse',
        'activated' => 1,
        'email' => 'suki@example.com',
        'company_id' => $company->id,
        'locale' => 'en-US',
        'employee_num' => '1234',
        'jobtitle' => 'Manager',
        'manager_id' => $manager->id,
        'department_id' => $department->id,
        'start_date' => '2025-04-01',
        'end_date' => '2025-05-25',
        // @todo:
        // 'vip' => '1',
        'autoassign_licenses' => 1,
        'remote' => 1,
        'location_id' => $location->id,
        'phone' => '555-5555',
        'website' => 'https://www.example.com',
        'address' => '23456 Main St',
        'city' => 'Waterside',
        'state' => 'CA',
        'country' => 'AE',
        'zip' => '12345',
        'notes' => 'some notes',
    ]);

    $suki = User::where('username', 'sukiwaterhouse')->first();

    $this->assertTrue($groupA->users->contains($suki));
    $this->assertTrue($groupB->users->contains($suki));
    $this->assertFalse($groupC->users->contains($suki));
});
