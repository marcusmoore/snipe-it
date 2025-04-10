<?php

use App\Models\User;

describe('permission checks', function () {
    test('permission required to view create page', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('users.create'))
            ->assertForbidden();
    });

    test('permission required to create user', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('users.store'), [
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
    $this->actingAs(User::factory()->createUsers()->create())
        ->post(route('users.store'), [
            'first_name' => 'Suki',
            'username' => 'suki',
            'password' => 'super-secret',
            'password_confirmation' => 'super-secret',
        ]);

    $this->assertDatabaseHas('users', [
        'first_name' => 'Suki',
        'username' => 'suki',
    ]);
});
