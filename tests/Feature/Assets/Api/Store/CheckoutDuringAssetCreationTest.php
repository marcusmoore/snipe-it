<?php

namespace Tests\Feature\Assets\Api\Store;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\TestCase;

class CheckoutDuringAssetCreationTest extends TestCase
{
    public function testAnAssetCanBeCheckedOutToUserOnStore()
    {
        $model = AssetModel::factory()->create();
        $status = Statuslabel::factory()->create();
        $user = User::factory()->createAssets()->create();
        $userAssigned = User::factory()->create();

        $this->settings->enableAutoIncrement();

        $response = $this->actingAsForApi($user)
            ->postJson(route('api.assets.store'), [
                'assigned_user' => $userAssigned->id,
                'model_id' => $model->id,
                'status_id' => $status->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->json();

        $asset = Asset::find($response['payload']['id']);

        $this->assertTrue($asset->adminuser->is($user));
        $this->assertTrue($asset->checkedOutToUser());
        $this->assertTrue($asset->assignedTo->is($userAssigned));
    }

    public function testAnAssetCanBeCheckedOutToLocationOnStore()
    {
        $model = AssetModel::factory()->create();
        $status = Statuslabel::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->createAssets()->create();

        $this->settings->enableAutoIncrement();

        $response = $this->actingAsForApi($user)
            ->postJson(route('api.assets.store'), [
                'assigned_location' => $location->id,
                'model_id' => $model->id,
                'status_id' => $status->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->json();

        $asset = Asset::find($response['payload']['id']);

        $this->assertTrue($asset->adminuser->is($user));
        $this->assertTrue($asset->checkedOutToLocation());
        $this->assertTrue($asset->location->is($location));
    }

    public function testAnAssetCanBeCheckedOutToAssetOnStore()
    {
        $model = AssetModel::factory()->create();
        $status = Statuslabel::factory()->create();
        $asset = Asset::factory()->create();
        $user = User::factory()->createAssets()->create();

        $this->settings->enableAutoIncrement();

        $response = $this->actingAsForApi($user)
            ->postJson(route('api.assets.store'), [
                'assigned_asset' => $asset->id,
                'model_id' => $model->id,
                'status_id' => $status->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->json();

        $apiAsset = Asset::find($response['payload']['id']);

        $this->assertTrue($apiAsset->adminuser->is($user));
        $this->assertTrue($apiAsset->checkedOutToAsset());
        // I think this makes sense, but open to a sanity check
        $this->assertTrue($asset->assignedAssets()->find($response['payload']['id'])->is($apiAsset));
    }
}
