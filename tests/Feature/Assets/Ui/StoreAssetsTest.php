<?php

use App\Models\User;

pest()->group('assets', 'ui');

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('hardware.create'))
        ->assertOk();
});
