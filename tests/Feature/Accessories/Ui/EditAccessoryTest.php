<?php

use App\Models\Accessory;
use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('accessories.edit', Accessory::factory()->create()->id))
        ->assertOk();
});
