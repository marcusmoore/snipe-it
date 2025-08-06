<?php

use App\Models\Consumable;
use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('consumables.show', Consumable::factory()->create()))
        ->assertOk();
});
