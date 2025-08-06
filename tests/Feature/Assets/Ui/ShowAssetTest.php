<?php

use App\Models\Asset;
use App\Models\User;

test('page for asset with missing model still renders', function () {
    $asset = Asset::factory()->create();

    $asset->model_id = null;
    $asset->forceSave();

    $asset->refresh();

    expect($asset->fresh()->model_id)->toBeNull('This test needs model_id to be null to be helpful.');

    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('hardware.show', $asset))
        ->assertOk();
});
