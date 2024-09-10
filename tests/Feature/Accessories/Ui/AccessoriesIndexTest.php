<?php

use App\Models\User;

test('permission required to view accessory list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('accessories.index'))
        ->assertForbidden();
});
