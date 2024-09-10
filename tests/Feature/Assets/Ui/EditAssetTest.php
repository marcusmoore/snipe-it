<?php

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\StatusLabel;
use App\Models\User;

test('permission required to view license', function () {
    $asset = Asset::factory()->create();
    $this->actingAs(User::factory()->create())
        ->get(route('hardware.edit', $asset))
        ->assertForbidden();
});

test('page can be accessed', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $response = $this->actingAs($user)->get(route('hardware.edit', $asset->id));
    $response->assertStatus(200);
});

test('asset edit post is redirected if redirect selection is index', function () {
    $asset = Asset::factory()->assignedToUser()->create();

    $this->actingAs(User::factory()->viewAssets()->editAssets()->create())
        ->from(route('hardware.edit', $asset))
        ->put(route('hardware.update', $asset),
            [
                'redirect_option' => 'index',
                'name' => 'New name',
                'asset_tags' => 'New Asset Tag',
                'status_id' => StatusLabel::factory()->create()->id,
                'model_id' => AssetModel::factory()->create()->id,
            ])
        ->assertStatus(302)
        ->assertRedirect(route('hardware.index'));
    $this->assertDatabaseHas('assets', ['asset_tag' => 'New Asset Tag']);
});

test('asset edit post is redirected if redirect selection is item', function () {
    $asset = Asset::factory()->create();

    $this->actingAs(User::factory()->viewAssets()->editAssets()->create())
        ->from(route('hardware.edit', $asset))
        ->put(route('hardware.update', $asset), [
            'redirect_option' => 'item',
            'name' => 'New name',
            'asset_tags' => 'New Asset Tag',
            'status_id' => StatusLabel::factory()->create()->id,
            'model_id' => AssetModel::factory()->create()->id,
        ])
        ->assertStatus(302)
        ->assertRedirect(route('hardware.show', ['hardware' => $asset->id]));

    $this->assertDatabaseHas('assets', ['asset_tag' => 'New Asset Tag']);
});
