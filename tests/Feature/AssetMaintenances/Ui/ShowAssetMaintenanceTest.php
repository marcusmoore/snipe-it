<?php

use App\Models\AssetMaintenance;
use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('maintenances.show', AssetMaintenance::factory()->create()->id))
        ->assertOk();
});
