<?php

use App\Models\Accessory;
use App\Models\User;

test('permission required to delete accessory', function () {
    $accessory = Accessory::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->deleteJson(route('api.accessories.destroy', $accessory))
        ->assertForbidden();
});
