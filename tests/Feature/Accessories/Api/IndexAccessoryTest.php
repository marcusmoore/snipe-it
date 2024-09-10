<?php

use App\Models\User;

test('permission required to view accessories index', function () {
    $this->actingAsForApi(User::factory()->create())
        ->getJson(route('api.accessories.index'))
        ->assertForbidden();
});
