<?php

use App\Models\PredefinedKit;
use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('kits.edit', PredefinedKit::factory()->create()->id))
        ->assertOk();
});
