<?php

use App\Models\User;

test('permission required to store accessory', function () {
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.accessories.store'))
        ->assertForbidden();
});
