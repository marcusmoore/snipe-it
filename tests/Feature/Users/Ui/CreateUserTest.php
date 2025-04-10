<?php

use App\Models\User;

test('permission required to view create page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('users.create'))
        ->assertForbidden();
});

test('create page renders', function () {
    $admin = User::factory()->createUsers()->create();

    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('users.create'))
        ->assertOk()
        ->assertDontSee($admin->first_name)
        ->assertDontSee($admin->last_name)
        ->assertDontSee($admin->email);
});
