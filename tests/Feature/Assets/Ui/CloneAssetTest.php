<?php

use App\Models\Asset;
use App\Models\User;

test('permission required to create asset model', function () {
    $asset = Asset::factory()->create();
    $this->actingAs(User::factory()->create())
        ->get(route('clone/hardware', $asset))
        ->assertForbidden();
});

test('page can be accessed', function () {
    $asset = Asset::factory()->create();
    $response = $this->actingAs(User::factory()->createAssets()->create())
        ->get(route('clone/hardware', $asset));
    $response->assertStatus(200);
});

test('asset can be cloned', function () {
    $asset_to_clone = Asset::factory()->create(['name'=>'Asset to clone']);
    $this->actingAs(User::factory()->createAssets()->create())
        ->get(route('clone/hardware', $asset_to_clone))
        ->assertOk()
        ->assertSee([
            'Asset to clone'
        ], false);
});
