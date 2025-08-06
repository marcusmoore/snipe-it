<?php

use App\Models\Department;
use App\Models\User;

test('requires permission to edit department', function () {
    $department = Department::factory()->create();
    $this->actingAsForApi(User::factory()->create())
        ->patchJson(route('api.departments.update', $department))
        ->assertForbidden();
});

test('can update department via patch', function () {
    $department = Department::factory()->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.departments.update', $department), [
            'name' => 'Test Department',
            'notes' => 'Test Note',
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->assertStatus(200)
        ->json();

    $department->refresh();
    expect($department->name)->toEqual('Test Department', 'Name was not updated');
    expect($department->notes)->toEqual('Test Note', 'Note was not updated');
});
