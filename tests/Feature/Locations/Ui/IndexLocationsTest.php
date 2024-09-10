<?php

use App\Models\User;

test('permission required to view locations list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('locations.index'))
        ->assertForbidden();
});

test('user can list locations', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('locations.index'))
        ->assertOk();
});
