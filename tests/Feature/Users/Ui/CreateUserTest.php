<?php

use App\Models\User;

test('permission required to create user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('users.create'))
        ->assertForbidden();
});

test('page renders', function () {
    $this->actingAs(User::factory()->createUsers()->create())
        ->get(route('users.create'))
        ->assertOk();
});

test('can create user', function () {
    $response = $this->actingAs(User::factory()->createUsers()->viewUsers()->create())
        ->from(route('users.index'))
        ->post(route('users.store'), [
            'first_name' => 'Test First Name',
            'last_name' => 'Test Last Name',
            'username' => 'testuser',
            'password' => 'testpassword1235!!',
            //'notes' => 'Test Note',
        ])
        ->assertStatus(302)
        ->assertRedirect(route('users.index'));

    $this->followRedirects($response)->assertSee('Success');
});
