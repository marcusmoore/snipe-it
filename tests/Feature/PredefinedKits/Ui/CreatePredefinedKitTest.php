<?php

use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('kits.create'))
        ->assertOk();
});
