<?php

use App\Models\Department;
use App\Models\Category;
use App\Models\User;

test('permission required to store department', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('departments.store'), [
            'name' => 'Test Department',
        ])
        ->assertStatus(403)
        ->assertForbidden();
});

test('user can edit departments', function () {
    $department = Department::factory()->create(['name' => 'Test Department']);
    expect(Department::where('name', 'Test Department')->exists())->toBeTrue();

    $response = $this->actingAs(User::factory()->superuser()->create())
        ->put(route('departments.update', ['department' => $department]), [
            'name' => 'Test Department Edited',
        ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('departments.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect(Department::where('name', 'Test Department Edited')->exists())->toBeTrue();
});
