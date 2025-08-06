<?php

use App\Models\Location;
use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('locations.show', Location::factory()->create()))
        ->assertOk();
});

test('denies access to regular user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('locations.show', Location::factory()->create()))
        ->assertStatus(403)
        ->assertForbidden();
});

test('denies print access to regular user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('locations.print_all_assigned', Location::factory()->create()))
        ->assertStatus(403)
        ->assertForbidden();
});

test('page renders for super admin', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('locations.print_all_assigned', Location::factory()->create()))
        ->assertOk();
});
