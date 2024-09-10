<?php

use App\Models\Accessory;
use App\Models\User;

test('permission required to show accessory', function () {
    $accessory = Accessory::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->getJson(route('api.accessories.show', $accessory))
        ->assertForbidden();
});
