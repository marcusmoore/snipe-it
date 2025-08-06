<?php

use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->viewUsers()->create())
        ->get(route('users.index'))
        ->assertOk();
});
