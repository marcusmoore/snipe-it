<?php

use App\Models\Department;
use App\Models\User;

test('requires permission to create department', function () {
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.departments.store'))
        ->assertForbidden();
});

test('can create department', function () {
    $response = $this->actingAsForApi(User::factory()->superuser()->create())
        ->postJson(route('api.departments.store'), [
            'name' => 'Test Department',
            'notes' => 'Test Note',
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->assertStatus(200)
        ->json();

    expect(Department::where('name', 'Test Department')->exists())->toBeTrue();

    $department = Department::find($response['payload']['id']);
    expect($department->name)->toEqual('Test Department');
    expect($department->notes)->toEqual('Test Note');
});
