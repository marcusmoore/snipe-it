<?php

use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('users.clone.show', User::factory()->create()))
        ->assertOk();
});
