<?php

use App\Models\Accessory;
use App\Models\User;

test('permission required to update accessory', function () {
    $accessory = Accessory::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->patchJson(route('api.accessories.update', $accessory))
        ->assertForbidden();
});
