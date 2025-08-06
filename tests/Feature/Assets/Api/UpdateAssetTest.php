<?php

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\CustomField;
use Illuminate\Support\Facades\Crypt;

test('that anon existent asset id returns error', function () {
    $this->actingAsForApi(User::factory()->editAssets()->createAssets()->create())
        ->patchJson(route('api.assets.update', 123456789))
        ->assertStatusMessageIs('error');
});

test('requires permission to update asset', function () {
    $asset = Asset::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->patchJson(route('api.assets.update', $asset->id))
        ->assertForbidden();
});

test('given permission update asset is allowed', function () {
    $asset = Asset::factory()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson(route('api.assets.update', $asset->id), [
            'name' => 'test'
        ])
        ->assertOk();
});

test('all asset attributes are stored', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $userAssigned = User::factory()->create();
    $company = Company::factory()->create();
    $location = Location::factory()->create();
    $model = AssetModel::factory()->create();
    $rtdLocation = Location::factory()->create();
    $status = Statuslabel::factory()->create();
    $supplier = Supplier::factory()->create();

    $response = $this->actingAsForApi($user)
        ->patchJson(route('api.assets.update', $asset->id), [
            'asset_eol_date' => '2024-06-02',
            'asset_tag' => 'random_string',
            'assigned_user' => $userAssigned->id,
            'company_id' => $company->id,
            'last_audit_date' => '2023-09-03 12:23:45',
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
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->json();

    $updatedAsset = Asset::find($response['payload']['id']);

    expect($updatedAsset->asset_eol_date)->toEqual('2024-06-02');
    expect($updatedAsset->asset_tag)->toEqual('random_string');
    expect($updatedAsset->assigned_to)->toEqual($userAssigned->id);
    expect($updatedAsset->company->is($company))->toBeTrue();
    expect($updatedAsset->location->is($location))->toBeTrue();
    expect($updatedAsset->model->is($model))->toBeTrue();
    expect($updatedAsset->name)->toEqual('A New Asset');
    expect($updatedAsset->notes)->toEqual('Some notes');
    expect($updatedAsset->order_number)->toEqual('5678');
    expect($updatedAsset->purchase_cost)->toEqual('123.45');
    expect($updatedAsset->purchase_date->is('2023-09-02'))->toBeTrue();
    expect($updatedAsset->requestable)->toEqual('1');
    expect($updatedAsset->defaultLoc->is($rtdLocation))->toBeTrue();
    expect($updatedAsset->serial)->toEqual('1234567890');
    expect($updatedAsset->assetstatus->is($status))->toBeTrue();
    expect($updatedAsset->supplier->is($supplier))->toBeTrue();
    expect($updatedAsset->warranty_months)->toEqual(10);

    //$this->assertEquals('2023-09-03 00:00:00', $updatedAsset->last_audit_date->format('Y-m-d H:i:s'));
    expect($updatedAsset->last_audit_date)->toEqual('2023-09-03 00:00:00');
});

test('updates period as comma separator for purchase cost', function () {
    $this->settings->set([
        'default_currency' => 'EUR',
        'digit_separator' => '1.234,56',
    ]);

    $original_asset = Asset::factory()->create();

    $response = $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.assets.update', $original_asset->id), [
            'asset_tag' => 'random-string',
            'model_id' => AssetModel::factory()->create()->id,
            'status_id' => Statuslabel::factory()->create()->id,
            // API also accepts string for comma separated values
            'purchase_cost' => '1.112,34',
        ])
        ->assertStatusMessageIs('success');

    $asset = Asset::find($response['payload']['id']);

    expect($asset->purchase_cost)->toEqual(1112.34);
});

test('updates float for purchase cost', function () {
    $this->settings->set([
        'default_currency' => 'EUR',
        'digit_separator' => '1.234,56',
    ]);

    $original_asset = Asset::factory()->create();

    $response = $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.assets.update', $original_asset->id), [
            'asset_tag' => 'random-string',
            'model_id' => AssetModel::factory()->create()->id,
            'status_id' => Statuslabel::factory()->create()->id,
            // API also accepts string for comma separated values
            'purchase_cost' => 12.34,
        ])
        ->assertStatusMessageIs('success');

    $asset = Asset::find($response['payload']['id']);

    expect($asset->purchase_cost)->toEqual(12.34);
});

test('updates usdecimal for purchase cost', function () {
    $this->settings->set([
        'default_currency' => 'EUR',
        'digit_separator' => '1,234.56',
    ]);

    $original_asset = Asset::factory()->create();

    $response = $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.assets.update', $original_asset->id), [
            'asset_tag' => 'random-string',
            'model_id' => AssetModel::factory()->create()->id,
            'status_id' => Statuslabel::factory()->create()->id,
            // API also accepts string for comma separated values
            'purchase_cost' => '5412.34', //NOTE - you cannot use thousands-separator here!!!!
        ])
        ->assertStatusMessageIs('success');

    $asset = Asset::find($response['payload']['id']);

    expect($asset->purchase_cost)->toEqual(5412.34);
});

test('updates float usdecimal for purchase cost', function () {
    $this->settings->set([
        'default_currency' => 'EUR',
        'digit_separator' => '1,234.56',
    ]);

    $original_asset = Asset::factory()->create();

    $response = $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.assets.update', $original_asset->id), [
            'asset_tag' => 'random-string',
            'model_id' => AssetModel::factory()->create()->id,
            'status_id' => Statuslabel::factory()->create()->id,
            // API also accepts string for comma separated values
            'purchase_cost' => 12.34,
        ])
        ->assertStatusMessageIs('success');

    $asset = Asset::find($response['payload']['id']);

    expect($asset->purchase_cost)->toEqual(12.34);
});

test('asset eol date is calculated if purchase date updated', function () {
    $asset = Asset::factory()->laptopMbp()->noPurchaseOrEolDate()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson((route('api.assets.update', $asset->id)), [
            'purchase_date' => '2021-01-01',
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->json();

    $asset->refresh();

    expect($asset->asset_eol_date)->toEqual('2024-01-01');
});

test('asset eol date is not calculated if purchase date not set', function () {
    $asset = Asset::factory()->laptopMbp()->noPurchaseOrEolDate()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson(route('api.assets.update', $asset->id), [
            'name' => 'test asset',
            'asset_eol_date' => '2022-01-01'
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->json();

    $asset->refresh();

    expect($asset->asset_eol_date)->toEqual('2022-01-01');
});

test('asset eol explicit is set if asset eol date is explicitly set', function () {
    $asset = Asset::factory()->laptopMbp()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson(route('api.assets.update', $asset->id), [
            'asset_eol_date' => '2025-01-01',
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->json();

    $asset->refresh();

    expect($asset->asset_eol_date)->toEqual('2025-01-01');
    expect($asset->eol_explicit)->toBeTrue();
});

test('asset tag cannot update to null value', function () {
    $asset = Asset::factory()->laptopMbp()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson(route('api.assets.update', $asset->id), [
            'asset_tag' => null,
        ])
        ->assertOk()
        ->assertStatusMessageIs('error');
});

test('asset tag cannot update to empty string value', function () {
    $asset = Asset::factory()->laptopMbp()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson(route('api.assets.update', $asset->id), [
            'asset_tag' => "",
        ])
        ->assertOk()
        ->assertStatusMessageIs('error');
});

test('model id cannot update to null value', function () {
    $asset = Asset::factory()->laptopMbp()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson(route('api.assets.update', $asset->id), [
            'model_id' => null
        ])
        ->assertOk()
        ->assertStatusMessageIs('error');
});

test('model id cannot update to empty string value', function () {
    $asset = Asset::factory()->laptopMbp()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson(route('api.assets.update', $asset->id), [
            'model_id' => ""
        ])
        ->assertOk()
        ->assertStatusMessageIs('error');
});

test('status id cannot update to null value', function () {
    $asset = Asset::factory()->laptopMbp()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson(route('api.assets.update', $asset->id), [
            'status_id' => null
        ])
        ->assertOk()
        ->assertStatusMessageIs('error');
});

test('status id cannot update to empty string value', function () {
    $asset = Asset::factory()->laptopMbp()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson(route('api.assets.update', $asset->id), [
            'status_id' => ""
        ])
        ->assertOk()
        ->assertStatusMessageIs('error');
});

test('if rtd location id is set without location id asset returns to default', function () {
    $location = Location::factory()->create();
    $asset = Asset::factory()->laptopMbp()->create([
        'location_id' => $location->id
    ]);
    $rtdLocation = Location::factory()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson(route('api.assets.update', $asset->id), [
            'rtd_location_id' => $rtdLocation->id
        ]);

    $asset->refresh();

    expect($asset->defaultLoc->is($rtdLocation))->toBeTrue();
    expect($asset->location->is($rtdLocation))->toBeTrue();
});

test('if location and rtd location are set location id is location', function () {
    $location = Location::factory()->create();
    $asset = Asset::factory()->laptopMbp()->create();
    $rtdLocation = Location::factory()->create();

    $this->actingAsForApi(User::factory()->editAssets()->create())
        ->patchJson(route('api.assets.update', $asset->id), [
            'rtd_location_id' => $rtdLocation->id,
            'location_id' => $location->id
        ]);

    $asset->refresh();

    expect($asset->defaultLoc->is($rtdLocation))->toBeTrue();
    expect($asset->location->is($location))->toBeTrue();
});

test('encrypted custom field can be updated', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    $field = CustomField::factory()->testEncrypted()->create();
    $asset = Asset::factory()->hasEncryptedCustomField($field)->create();
    $superuser = User::factory()->superuser()->create();

    $this->actingAsForApi($superuser)
        ->patchJson(route('api.assets.update', $asset->id), [
            $field->db_column_name() => 'This is encrypted field'
        ])
        ->assertStatusMessageIs('success')
        ->assertOk();

    $asset->refresh();
    expect(Crypt::decrypt($asset->{$field->db_column_name()}))->toEqual('This is encrypted field');
});

test('permission needed to update encrypted field', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    $field = CustomField::factory()->testEncrypted()->create();
    $asset = Asset::factory()->hasEncryptedCustomField($field)->create();
    $normal_user = User::factory()->editAssets()->create();

    $asset->{$field->db_column_name()} = Crypt::encrypt("encrypted value should not change");
    $asset->save();

    // test that a 'normal' user *cannot* change the encrypted custom field
    $this->actingAsForApi($normal_user)
        ->patchJson(route('api.assets.update', $asset->id), [
            $field->db_column_name() => 'Some Other Value Entirely!'
        ])
        ->assertStatusMessageIs('success')
        ->assertOk()
        ->assertMessagesAre('Asset updated successfully, but encrypted custom fields were not due to permissions');

    $asset->refresh();
    expect(Crypt::decrypt($asset->{$field->db_column_name()}))->toEqual("encrypted value should not change");
});

test('checkout to user on asset update', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $assigned_user = User::factory()->create();

    $response = $this->actingAsForApi($user)
        ->patchJson(route('api.assets.update', $asset->id), [
            'assigned_user' => $assigned_user->id,
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->json();

    $asset->refresh();
    expect($asset->assigned_to)->toEqual($assigned_user->id);
    expect('App\Models\User')->toEqual($asset->assigned_type);
});

test('checkout to user with assigned to and assigned type', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $assigned_user = User::factory()->create();

    $response = $this->actingAsForApi($user)
        ->patchJson(route('api.assets.update', $asset->id), [
            'assigned_to' => $assigned_user->id,
            'assigned_type' => User::class
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->json();

    $asset->refresh();
    expect($asset->assigned_to)->toEqual($assigned_user->id);
    expect('App\Models\User')->toEqual($asset->assigned_type);
});

test('checkout to user with assigned to without assigned type', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $assigned_user = User::factory()->create();

    $response = $this->actingAsForApi($user)
        ->patchJson(route('api.assets.update', $asset->id), [
            'assigned_to' => $assigned_user->id,
//                'assigned_type' => User::class //deliberately omit assigned_type
        ])
        ->assertOk()
        ->assertStatusMessageIs('error');

    $asset->refresh();
    $this->assertNotEquals($assigned_user->id, $asset->assigned_to);
    $this->assertNotEquals($asset->assigned_type, 'App\Models\User');
    expect($response->json('messages.assigned_type'))->not->toBeNull();
});

test('checkout to user with assigned to with bad assigned type', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $assigned_user = User::factory()->create();

    $response = $this->actingAsForApi($user)
        ->patchJson(route('api.assets.update', $asset->id), [
            'assigned_to'   => $assigned_user->id,
            'assigned_type' => 'more_deliberate_nonsense' //deliberately bad assigned_type
        ])
        ->assertOk()
        ->assertStatusMessageIs('error');

    $asset->refresh();
    $this->assertNotEquals($assigned_user->id, $asset->assigned_to);
    $this->assertNotEquals($asset->assigned_type, 'App\Models\User');
    expect($response->json('messages.assigned_type'))->not->toBeNull();
});

test('checkout to user without assigned to with assigned type', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $assigned_user = User::factory()->create();

    $response = $this->actingAsForApi($user)
        ->patchJson(route('api.assets.update', $asset->id), [
            //'assigned_to'   => $assigned_user->id, // deliberately omit assigned_to
            'assigned_type' => User::class
        ])
        ->assertOk()
        ->assertStatusMessageIs('error');

    $asset->refresh();
    $this->assertNotEquals($assigned_user->id, $asset->assigned_to);
    $this->assertNotEquals($asset->assigned_type, 'App\Models\User');
    expect($response->json('messages.assigned_to'))->not->toBeNull();
});

test('checkout to deleted user fails on asset update', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $assigned_user = User::factory()->deleted()->create();

    $this->actingAsForApi($user)
        ->patchJson(route('api.assets.update', $asset->id), [
            'assigned_user' => $assigned_user->id,
        ])
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->json();

    $asset->refresh();
    expect($asset->assigned_to)->toBeNull();
    expect($asset->assigned_type)->toBeNull();
});

test('checkout to location on asset update', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $assigned_location = Location::factory()->create();

    $this->actingAsForApi($user)
        ->patchJson(route('api.assets.update', $asset->id), [
            'assigned_location' => $assigned_location->id,
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->json();

    $asset->refresh();
    expect($asset->assigned_to)->toEqual($assigned_location->id);
    expect('App\Models\Location')->toEqual($asset->assigned_type);
});

test('checkout to deleted location fails on asset update', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $assigned_location = Location::factory()->deleted()->create();

    $this->actingAsForApi($user)
        ->patchJson(route('api.assets.update', $asset->id), [
            'assigned_location' => $assigned_location->id,
        ])
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->json();

    $asset->refresh();
    expect($asset->assigned_to)->toBeNull();
    expect($asset->assigned_type)->toBeNull();
});

test('checkout asset on asset update', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $assigned_asset = Asset::factory()->create();

    $this->actingAsForApi($user)
        ->patchJson(route('api.assets.update', $asset->id), [
            'assigned_asset'   => $assigned_asset->id,
            'checkout_to_type' => 'user',
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->json();

    $asset->refresh();
    expect($asset->assigned_to)->toEqual($assigned_asset->id);
    expect('App\Models\Asset')->toEqual($asset->assigned_type);
});

test('checkout to deleted asset fails on asset update', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->editAssets()->create();
    $assigned_asset = Asset::factory()->deleted()->create();

    $this->actingAsForApi($user)
        ->patchJson(route('api.assets.update', $asset->id), [
            'assigned_asset' => $assigned_asset->id,
        ])
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->json();

    $asset->refresh();
    expect($asset->assigned_to)->toBeNull();
    expect($asset->assigned_type)->toBeNull();
});

test('asset cannot be updated by user in separate company', function () {
    $this->settings->enableMultipleFullCompanySupport();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->editAssets()->create([
        'company_id' => $companyA->id,
    ]);
    $userB = User::factory()->editAssets()->create([
        'company_id' => $companyB->id,
    ]);
    $asset = Asset::factory()->create([
        'created_by'    => $userA->id,
        'company_id' => $companyA->id,
    ]);

    $this->actingAsForApi($userB)
        ->patchJson(route('api.assets.update', $asset->id), [
            'name' => 'test name'
        ])
        ->assertStatusMessageIs('error');

    $this->actingAsForApi($userA)
        ->patchJson(route('api.assets.update', $asset->id), [
            'name' => 'test name'
        ])
        ->assertStatusMessageIs('success');
});

test('custom field cannot be updated if not on current asset model', function () {
    $this->markIncompleteIfMySQL('Custom Field Tests do not work in MySQL');

    $customField = CustomField::factory()->create();
    $customField2 = CustomField::factory()->create();
    $asset = Asset::factory()->hasMultipleCustomFields([$customField])->create();
    $user = User::factory()->editAssets()->create();

    // successful
    $this->actingAsForApi($user)->patchJson(route('api.assets.update', $asset->id), [
        $customField->db_column_name() => 'test attribute',
    ])->assertStatusMessageIs('success');

    // custom field exists, but not on this asset model
    $this->actingAsForApi($user)->patchJson(route('api.assets.update', $asset->id), [
        $customField2->db_column_name() => 'test attribute',
    ])->assertStatusMessageIs('error');

    // custom field does not exist
    $this->actingAsForApi($user)->patchJson(route('api.assets.update', $asset->id), [
        '_snipeit_non_existent_custom_field_50' => 'test attribute',
    ])->assertStatusMessageIs('error');
});
