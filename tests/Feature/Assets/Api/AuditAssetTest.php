<?php

use App\Models\Asset;
use App\Models\User;

test('that anon existent asset id returns error', function () {
    $this->actingAsForApi(User::factory()->auditAssets()->create())
        ->postJson(route('api.asset.audit', 123456789))
        ->assertStatusMessageIs('error');
});

test('requires permission to audit asset', function () {
    $asset = Asset::factory()->create();
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.asset.audit', $asset))
        ->assertForbidden();
});

test('legacy asset audit is saved', function () {
    $asset = Asset::factory()->create();
    $this->actingAsForApi(User::factory()->auditAssets()->create())
        ->postJson(route('api.asset.audit.legacy'), [
            'asset_tag' => $asset->asset_tag,
            'note' => 'test',
        ])
        ->assertStatusMessageIs('success')
        ->assertJson(
            [
                'messages' =>trans('admin/hardware/message.audit.success'),
                'payload' => [
                    'id' => $asset->id,
                    'asset_tag' => $asset->asset_tag,
                    'note' => 'test'
                ],
            ])
        ->assertStatus(200);
});

test('asset audit is saved', function () {
    $asset = Asset::factory()->create();
    $this->actingAsForApi(User::factory()->auditAssets()->create())
        ->postJson(route('api.asset.audit', $asset), [
            'note' => 'test'
        ])
        ->assertStatusMessageIs('success')
        ->assertJson(
            [
                'messages' =>trans('admin/hardware/message.audit.success'),
                'payload' => [
                    'id' => $asset->id,
                    'asset_tag' => $asset->asset_tag,
                    'note' => 'test'
                ],
            ])
        ->assertStatus(200);
    $this->assertHasTheseActionLogs($asset, ['create', 'audit']);
});
