<?php

use App\Models\User;

test('permission required to view license list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('licenses.index'))
        ->assertForbidden();
});

test('user can list licenses', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('licenses.index'))
        ->assertOk();
});
