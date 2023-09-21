<?php

namespace Tests\Feature\Api\Assets;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithSettings;
use Tests\TestCase;

class AssetStoreTest extends TestCase
{
    use InteractsWithSettings;

    public function testRequiresPermissionToCreateAsset()
    {
        $this->actingAsForApi(User::factory()->create())
            ->postJson(route('api.assets.store'))
            ->assertForbidden();
    }

    public function testCanCreateAsset()
    {
        $company = Company::factory()->create();
        $location = Location::factory()->create();
        $model = AssetModel::factory()->create();
        $rtdLocation = Location::factory()->create();
        $status = Statuslabel::factory()->create();
        $supplier = Supplier::factory()->create();
        $user = User::factory()->createAssets()->create();
        $userAssigned = User::factory()->create();

        $results = $this->actingAsForApi($user)
            ->postJson(route('api.assets.store'), [
                'archived' => true,
                'asset_eol_date' => '2024-06-02',
                'asset_tag' => 'random_string',
                // @todo: This isn't in the docs but it's in the controller
                'assigned_to' => $userAssigned->id,
                'company_id' => $company->id,
                'depreciate' => true,
                'last_audit_date' => '2023-09-03',
                'location_id' => $location->id,
                'model_id' => $model->id,
                'name' => 'A New Asset',
                'notes' => 'Some notes',
                'order_number' => '5678',
                'purchase_cost' => '123.45',
                'purchase_date' => '2023-09-02',
                'requestable' => true,
                'rtd_location_id' => $rtdLocation->id,
                'serial' => '1234567890',
                'status_id' => $status->id,
                'supplier_id' => $supplier->id,
                'warranty_months' => 10,
            ])->assertOk()->json();

        $this->assertEquals('success', $results['status']);

        $asset = Asset::find($results['payload']['id']);

        $this->assertTrue($asset->adminuser->is($user));
        // @todo: this is explicitly set 0 in the controller but they docs say they are customizable
        // $this->assertTrue($asset->archived);
        // @todo: This isn't in the docs but it's in the controller
        $this->assertEquals('2024-06-02', $asset->asset_eol_date);
        $this->assertEquals('random_string', $asset->asset_tag);
        // @todo:
        // $this->assertTrue($asset->assignedTo->is($userAssigned));
        // @todo: This is not in the docs but it's in the controller
        $this->assertTrue($asset->company->is($company));
        // @todo: this is explicitly set 0 in the controller but they docs say they are customizable
        // $this->assertTrue($asset->depreciate);
        // @todo: this is in the docs but not the controller
        // $this->assertEquals('2023-09-03', $asset->last_audit_date);
        // @todo: this is set to rtd_location_id in the controller but customizable in the docs
        // $this->assertTrue($asset->location->is($location));
        $this->assertTrue($asset->model->is($model));
        $this->assertEquals('A New Asset', $asset->name);
        $this->assertEquals('Some notes', $asset->notes);
        $this->assertEquals('5678', $asset->order_number);
        $this->assertEquals('123.45', $asset->purchase_cost);
        $this->assertTrue($asset->purchase_date->is('2023-09-02'));
        $this->assertEquals('1', $asset->requestable);
        $this->assertTrue($asset->defaultLoc->is($rtdLocation));
        $this->assertEquals('1234567890', $asset->serial);
        $this->assertTrue($asset->assetstatus->is($status));
        $this->assertTrue($asset->supplier->is($supplier));
        $this->assertEquals(10, $asset->warranty_months);
    }

    public function testSettingCompanyWithCompanyScopingEnabled()
    {
        $this->markTestIncomplete();
    }

    public function testCustomFields()
    {
        $this->markTestIncomplete('❓');

    }

    public function testCanCheckoutToUserWhenCreatingAsset()
    {
        $this->markTestIncomplete();

    }

    public function testCanCheckoutToAssetWhenCreatingAsset()
    {
        $this->markTestIncomplete();

    }

    public function testCanCheckoutToLocationWhenCreatingAsset()
    {
        $this->markTestIncomplete();

    }
}
