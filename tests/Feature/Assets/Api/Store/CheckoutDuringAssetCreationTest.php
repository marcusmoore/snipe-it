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

    private AssetModel $model;
    private Statuslabel $status;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings->enableAutoIncrement();

        $this->model = AssetModel::factory()->create();
        $this->status = Statuslabel::factory()->create();
        $this->actor = User::factory()->createAssets()->create();
    }

    public function testAnAssetCanBeCheckedOutToUserOnStore()
    {
        $userAssigned = User::factory()->create();

        $response = $this->actingAsForApi($this->actor)
            ->postJson(route('api.assets.store'), [
                'assigned_user' => $userAssigned->id,
                'model_id' => $this->model->id,
                'status_id' => $this->status->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->json();

        $asset = Asset::find($response['payload']['id']);

        $this->assertTrue($asset->adminuser->is($this->actor));
        $this->assertTrue($asset->checkedOutToUser());
        $this->assertTrue($asset->assignedTo->is($userAssigned));
    }

    public function testAnAssetCanBeCheckedOutToLocationOnStore()
    {
        $location = Location::factory()->create();

        $response = $this->actingAsForApi($this->actor)
            ->postJson(route('api.assets.store'), [
                'assigned_location' => $location->id,
                'model_id' => $this->model->id,
                'status_id' => $this->status->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->json();

        $asset = Asset::find($response['payload']['id']);

        $this->assertTrue($asset->adminuser->is($this->actor));
        $this->assertTrue($asset->checkedOutToLocation());
        $this->assertTrue($asset->location->is($location));
    }

    public function testAnAssetCanBeCheckedOutToAssetOnStore()
    {
        $asset = Asset::factory()->create();

        $response = $this->actingAsForApi($this->actor)
            ->postJson(route('api.assets.store'), [
                'assigned_asset' => $asset->id,
                'model_id' => $this->model->id,
                'status_id' => $this->status->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->json();

        $apiAsset = Asset::find($response['payload']['id']);

        $this->assertTrue($apiAsset->adminuser->is($this->actor));
        $this->assertTrue($apiAsset->checkedOutToAsset());
        // I think this makes sense, but open to a sanity check
        $this->assertTrue($asset->assignedAssets()->find($response['payload']['id'])->is($apiAsset));
    }

    public function testCanCheckoutAssetToUserViaAssignedToAndAssignedTypeFields()
    {
        $userAssigned = User::factory()->create();

        $response = $this->actingAsForApi($this->actor)
            ->postJson(route('api.assets.store'), [
                'assigned_to' => $userAssigned->id,
                'assigned_type' => 'user',
                'model_id' => $this->model->id,
                'status_id' => $this->status->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->json();

        $asset = Asset::find($response['payload']['id']);

        $this->assertTrue($asset->adminuser->is($this->actor));
        $this->assertTrue($asset->checkedOutToUser());
        $this->assertTrue($asset->assignedTo->is($userAssigned));
    }

    public function testCanCheckoutAssetToLocationViaAssignedToAndAssignedTypeFields()
    {
        $location = Location::factory()->create();

        $response = $this->actingAsForApi($this->actor)
            ->postJson(route('api.assets.store'), [
                'assigned_to' => $location->id,
                'assigned_type' => 'location',
                'model_id' => $this->model->id,
                'status_id' => $this->status->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->json();

        $asset = Asset::find($response['payload']['id']);

        $this->assertTrue($asset->adminuser->is($this->actor));
        $this->assertTrue($asset->checkedOutToLocation());
        $this->assertTrue($asset->location->is($location));
    }

    public function testCanCheckoutAssetToAssetViaAssignedToAndAssignedTypeFields()
    {
        $asset = Asset::factory()->create();

        $response = $this->actingAsForApi($this->actor)
            ->postJson(route('api.assets.store'), [
                'assigned_to' => $asset->id,
                'assigned_type' => 'asset',
                'model_id' => $this->model->id,
                'status_id' => $this->status->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->json();

        $apiAsset = Asset::find($response['payload']['id']);

        $this->assertTrue($apiAsset->adminuser->is($this->actor));
        $this->assertTrue($apiAsset->checkedOutToAsset());
        $this->assertTrue($asset->assignedAssets()->find($response['payload']['id'])->is($apiAsset));
    }

    public function testCannotProvideAssignedAssignedToAndAssignedTypeAtTheSameTime()
    {
        // $this->markTestIncomplete();

        $userAssigned = User::factory()->create();

        $response = $this->actingAsForApi($this->actor)
            ->postJson(route('api.assets.store'), [
                // assigned_user, assigned_asset, assigned_location...
                'assigned_user' => $userAssigned->id,
                'assigned_to' => $userAssigned->id,
                'assigned_type' => 'user',
                'model_id' => $this->model->id,
                'status_id' => $this->status->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->json();

        dd($response);
    }

    // @todo: ensure user/location/asset exists
}
