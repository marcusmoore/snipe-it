<?php

use App\Models\Department;
use App\Models\Company;
use App\Models\User;

test('permission required to create department', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('departments.store'), [
            'name' => 'Test Department',
            'company_id' => Company::factory()->create()->id
        ])
        ->assertForbidden();
});

test('user can create departments', function () {
    expect(Department::where('name', 'Test Department')->exists())->toBeFalse();

    $this->actingAs(User::factory()->superuser()->create())
        ->post(route('departments.store'), [
            'name' => 'Test Department',
            'company_id' => Company::factory()->create()->id
        ])
        ->assertRedirect(route('departments.index'));

    expect(Department::where('name', 'Test Department')->exists())->toBeTrue();
});
