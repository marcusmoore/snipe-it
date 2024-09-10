<?php

use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\User;

test('user with permissions can access page', function () {
    $user = User::factory()->viewAssets()->deleteAssets()->editAssets()->create();
    $assets = Asset::factory()->count(2)->create();

    $id_array = $assets->pluck('id')->toArray();

    $this->actingAs($user)->post('/hardware/bulkedit', [
        'ids'          => $id_array,
        'order'        => 'asc',
        'bulk_actions' => 'delete',
        'sort'         => 'id'
    ])->assertStatus(200);
});

test('standard user cannot access page', function () {
    $user = User::factory()->create();
    $assets = Asset::factory()->count(2)->create();

    $id_array = $assets->pluck('id')->toArray();

    $this->actingAs($user)->post('/hardware/bulkdelete', [
        'ids'          => $id_array,
        'bulk_actions' => 'delete',
    ])->assertStatus(403);
});

test('page redirect from interstitial if no assets selected to delete', function () {
    $user = User::factory()->viewAssets()->deleteAssets()->editAssets()->create();
    $response = $this->actingAs($user)
        ->post('/hardware/bulkdelete', [
            'ids'          => null,
            'bulk_actions' => 'delete',
    ])
    ->assertStatus(302)
    ->assertRedirect(route('hardware.index'));

    $this->followRedirects($response)->assertSee('alert-danger');
});

test('page redirect from interstitial if no assets selected to restore', function () {
    $user = User::factory()->viewAssets()->deleteAssets()->editAssets()->create();
    $response = $this->actingAs($user)
        ->from(route('hardware.index'))
        ->post('/hardware/bulkrestore', [
            'ids'          => null,
            'bulk_actions' => 'delete',
        ])
        ->assertStatus(302)
        ->assertRedirect(route('hardware.index'));

    $this->followRedirects($response)->assertSee('alert-danger');
});

test('bulk delete selected assets from interstitial', function () {
    $user = User::factory()->viewAssets()->deleteAssets()->editAssets()->create();
    $assets = Asset::factory()->count(2)->create();

    $id_array = $assets->pluck('id')->toArray();

    $response = $this->actingAs($user)
        ->from(route('hardware/bulkedit'))
        ->post('/hardware/bulkdelete', [
        'ids'          => $id_array,
        'bulk_actions' => 'delete',
    ])->assertStatus(302);

    Asset::findMany($id_array)->each(function (Asset $asset)  {
        expect($asset->deleted_at)->not->toBeNull();
    });

    $this->followRedirects($response)->assertSee('alert-success');
});

test('bulk restore selected assets from interstitial', function () {
    $user = User::factory()->viewAssets()->deleteAssets()->editAssets()->create();
    $asset = Asset::factory()->deleted()->create();

    $asset->refresh();
    $id_array = $asset->pluck('id')->toArray();

    // Check that the assets are deleted
    Asset::findMany($id_array)->each(function (Asset $asset)  {
        expect($asset->deleted_at)->toBeNull();
    });

    $response = $this->actingAs($user)
        ->from(route('hardware/bulkedit'))
        ->post(route('hardware/bulkrestore'), [
            'ids'          => [$asset->id],
        ])->assertStatus(302);

    $this->followRedirects($response)->assertSee('alert-success');

    Asset::findMany($id_array)->each(function (Asset $asset)  {
        expect($asset->deleted_at)->toBeNull();
    });
});

test('action log created upon bulk delete', function () {
    $user = User::factory()->viewAssets()->deleteAssets()->editAssets()->create();
    $asset = Asset::factory()->create();

    $this->actingAs($user)
        ->from(route('hardware/bulkedit'))
        ->post('/hardware/bulkdelete', [
            'ids'          => [$asset->id],
            'bulk_actions' => 'delete',
        ]);

    $this->assertDatabaseHas('action_logs',
        [
            'action_type' => 'delete',
            'target_id' => null,
            'target_type' => null,
            'item_id' => $asset->id,
            'item_type' => Asset::class,
        ]
    );
});

test('action log created upon bulk restore', function () {
    $user = User::factory()->viewAssets()->deleteAssets()->editAssets()->create();
    $asset = Asset::factory()->deleted()->create();

    $this->actingAs($user)
        ->from(route('hardware/bulkedit'))
        ->post(route('hardware/bulkrestore'), [
            'ids'          => [$asset->id],
            'bulk_actions' => 'restore',
        ]);

    $this->assertDatabaseHas('action_logs',
        [
            'action_type' => 'restore',
            'target_id' => null,
            'target_type' => null,
            'item_id' => $asset->id,
            'item_type' => Asset::class,
        ]
    );
});
