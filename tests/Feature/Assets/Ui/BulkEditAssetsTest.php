<?php

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\CustomField;
use App\Models\Statuslabel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

pest()->group('assets', 'ui');

test('user with permissions can access page', function () {
    $user = User::factory()->viewAssets()->editAssets()->create();
    $assets = Asset::factory()->count(2)->create();

    $id_array = $assets->pluck('id')->toArray();

    $this->actingAs($user)->post('/hardware/bulkedit', [
        'ids'          => $id_array,
        'order'        => 'asc',
        'bulk_actions' => 'edit',
        'sort'         => 'id'
    ])->assertStatus(200);
});

test('standard user cannot access page', function () {
    $user = User::factory()->create();
    $assets = Asset::factory()->count(2)->create();

    $id_array = $assets->pluck('id')->toArray();

    $this->actingAs($user)->post('/hardware/bulkedit', [
        'ids'          => $id_array,
        'order'        => 'asc',
        'bulk_actions' => 'edit',
        'sort'         => 'id'
    ])->assertStatus(403);
});

test('bulk edit assets accepts all possible attributes', function () {
    // sets up all needed models and attributes on the assets
    // this test does not deal with custom fields - will be dealt with in separate cases
    $status1 = Statuslabel::factory()->create();
    $status2 = Statuslabel::factory()->create();
    $model1 = AssetModel::factory()->create();
    $model2 = AssetModel::factory()->create();
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();
    $assets = Asset::factory()->count(10)->create([
        'name'             => 'Old Asset Name',
        'purchase_date'    => '2023-01-01',
        'expected_checkin' => '2023-01-01',
        'status_id'        => $status1->id,
        'model_id'         => $model1->id,
        // skipping locations on this test, it deserves it's own test
        'purchase_cost'    => 1234.90,
        'supplier_id'      => $supplier1->id,
        'company_id'       => $company1->id,
        'order_number'     => '123456',
        'warranty_months'  => 24,
        'next_audit_date'  => '2024-06-01',
        'requestable'      => false
    ]);

    // gets the ids together to submit to the endpoint
    $id_array = $assets->pluck('id')->toArray();

    // submits the ids and new values for each attribute
    $this->actingAs(User::factory()->editAssets()->create())->post(route('hardware/bulksave'), [
        'ids'              => $id_array,
        'name'             => 'New Asset Name',
        'purchase_date'    => '2024-01-01',
        'expected_checkin' => '2024-01-01',
        'status_id'        => $status2->id,
        'model_id'         => $model2->id,
        'purchase_cost'    => 5678.92,
        'supplier_id'      => $supplier2->id,
        'company_id'       => $company2->id,
        'order_number'     => '7890',
        'warranty_months'  => 36,
        'next_audit_date'  => '2025-01-01',
        'requestable'      => true
    ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors();

    // asserts that each asset has the updated values
    Asset::findMany($id_array)->each(function (Asset $asset) use ($status2, $model2, $supplier2, $company2) {
        expect($asset->purchase_date->format('Y-m-d'))->toEqual('2024-01-01');
        expect($asset->expected_checkin->format('Y-m-d'))->toEqual('2024-01-01');
        expect($asset->status_id)->toEqual($status2->id);
        expect($asset->name)->toEqual('New Asset Name');
        expect($asset->model_id)->toEqual($model2->id);
        expect($asset->purchase_cost)->toEqual(5678.92);
        expect($asset->supplier_id)->toEqual($supplier2->id);
        expect($asset->company_id)->toEqual($company2->id);
        expect($asset->order_number)->toEqual(7890);
        expect($asset->warranty_months)->toEqual(36);
        expect($asset->next_audit_date)->toEqual('2025-01-01');
        // shouldn't requestable be cast as a boolean??? it's not.
        expect($asset->requestable)->toEqual(1);
    });
});

test('bulk edit assets nulls out fields if selected', function () {
    // sets up all needed models and attributes on the assets
    // this test does not deal with custom fields - will be dealt with in separate cases
    $status1 = Statuslabel::factory()->create();
    $status2 = Statuslabel::factory()->create();
    $model1 = AssetModel::factory()->create();
    $model2 = AssetModel::factory()->create();
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();
    $assets = Asset::factory()->count(10)->create([
        'name'             => 'Old Asset Name',
        'purchase_date'    => '2023-01-01',
        'expected_checkin' => '2023-01-01',
        'status_id'        => $status1->id,
        'model_id'         => $model1->id,
        // skipping locations on this test, it deserves it's own test
        'purchase_cost'    => 1234.90,
        'supplier_id'      => $supplier1->id,
        'company_id'       => $company1->id,
        'order_number'     => '123456',
        'warranty_months'  => 24,
        'next_audit_date'  => '2024-06-01',
        'requestable'      => false
    ]);

    // gets the ids together to submit to the endpoint
    $id_array = $assets->pluck('id')->toArray();

    // submits the ids and new values for each attribute
    $this->actingAs(User::factory()->editAssets()->create())->post(route('hardware/bulksave'), [
        'ids'              => $id_array,
        'null_name'        => '1',
        'null_purchase_date'    => '1',
        'null_expected_checkin_date' => '1',
        'null_next_audit_date'        => '1',
        'status_id'        => $status2->id,
        'model_id'         => $model2->id,
    ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors();

    // asserts that each asset has the updated values
    Asset::findMany($id_array)->each(function (Asset $asset) use ($status2, $model2, $supplier2, $company2) {
        expect($asset->name)->toBeNull();
        expect($asset->purchase_date)->toBeNull();
        expect($asset->expected_checkin)->toBeNull();
        expect($asset->next_audit_date)->toBeNull();
    });
});

test('bulk edit assets accepts and updates unencrypted custom fields', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    CustomField::factory()->ram()->create();
    CustomField::factory()->cpu()->create();

    // when getting the custom field directly from the factory the field has not been fully created yet
    // so we have to do a query afterwards to get the actual model :shrug:
    $ram = CustomField::where('name', 'RAM')->first();
    $cpu = CustomField::where('name', 'CPU')->first();

    $assets = Asset::factory()->count(10)->hasMultipleCustomFields([$ram, $cpu])->create([
        $ram->db_column => 8,
        $cpu->db_column => '2.1',
    ]);

    $id_array = $assets->pluck('id')->toArray();

    $this->actingAs(User::factory()->editAssets()->create())->post(route('hardware/bulksave'), [
        'ids'           => $id_array,
        $ram->db_column => 16,
        $cpu->db_column => '4.1',
    ])->assertStatus(302);

    Asset::findMany($id_array)->each(function (Asset $asset) use ($ram, $cpu) {
        expect($asset->{$ram->db_column})->toEqual(16);
        expect($asset->{$cpu->db_column})->toEqual('4.1');
    });
});

test('bulk edit assets nulls custom fields if selected', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    CustomField::factory()->ram()->create();
    CustomField::factory()->cpu()->create();
    CustomField::factory()->phone()->create();

    // when getting the custom field directly from the factory the field has not been fully created yet
    // so we have to do a query afterwards to get the actual model :shrug:
    $ram = CustomField::where('name', 'RAM')->first();
    $cpu = CustomField::where('name', 'CPU')->first();
    $phone = CustomField::where('name', 'Phone Number')->first();

    $assets = Asset::factory()->count(10)->hasMultipleCustomFields([$ram, $cpu])->create([
        $ram->db_column => 8,
        $cpu->db_column => '2.1',
    ]);

    $id_array = $assets->pluck('id')->toArray();

    $this->actingAs(User::factory()->editAssets()->create())->post(route('hardware/bulksave'), [
        'ids'                    => $id_array,
        $ram->db_column          => 16,
        $cpu->db_column          => '4.1',
        'null'.$phone->db_column => '8304997586',
    ])->assertStatus(302);

    $this->actingAs(User::factory()->editAssets()->create())->post(route('hardware/bulksave'), [
        'ids'                  => $id_array,
        null.$phone->db_column => 1,
    ]);

    Asset::findMany($id_array)->each(function (Asset $asset) use ($ram, $cpu, $phone) {
        expect($asset->{$ram->db_column})->toEqual(16);
        expect($asset->{$cpu->db_column})->toEqual('4.1');
        expect($asset->{$phone->db_column})->toEqual(null);
    });
});

test('bulk edit assets accepts and updates encrypted custom fields', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    CustomField::factory()->testEncrypted()->create();

    $encrypted = CustomField::where('name', 'Test Encrypted')->first();

    $assets = Asset::factory()->count(10)->hasEncryptedCustomField($encrypted)->create([
        $encrypted->db_column => Crypt::encrypt('Original Encrypted Text'),
    ]);

    $id_array = $assets->pluck('id')->toArray();

    $this->actingAs(User::factory()->admin()->create())->post(route('hardware/bulksave'), [
        'ids'                 => $id_array,
        $encrypted->db_column => 'New Encrypted Text',
    ])->assertStatus(302);

    Asset::findMany($id_array)->each(function (Asset $asset) use ($encrypted) {
        expect(Crypt::decrypt($asset->{$encrypted->db_column}))->toEqual('New Encrypted Text');
    });
});

test('bulk edit assets requires admin to update encrypted custom fields', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on mysql');
    $edit_user = User::factory()->editAssets()->create();
    $admin_user = User::factory()->admin()->create();

    CustomField::factory()->testEncrypted()->create();

    $encrypted = CustomField::where('name', 'Test Encrypted')->first();

    $admin_assets = Asset::factory()->count(5)->hasEncryptedCustomField($encrypted)->create([
        $encrypted->db_column => Crypt::encrypt('Original Encrypted Text'),
    ]);

    $standard_assets = Asset::factory()->count(5)->hasEncryptedCustomField($encrypted)->create([
        $encrypted->db_column => Crypt::encrypt('Original Encrypted Text'),
    ]);

    $admin_id_array = $admin_assets->pluck('id')->toArray();
    $standard_id_array = $standard_assets->pluck('id')->toArray();

    $this->actingAs($admin_user)->post(route('hardware/bulksave'), [
        'ids'                 => $admin_id_array,
        $encrypted->db_column => 'New Encrypted Text',
    ])->assertStatus(302);

    // do we want to return an error when this happens???
    $this->actingAs($edit_user)->post(route('hardware/bulksave'), [
        'ids'                 => $standard_id_array,
        $encrypted->db_column => 'New Encrypted Text',
    ])->assertStatus(302);

    Asset::findMany($admin_id_array)->each(function (Asset $asset) use ($encrypted) {
        expect(Crypt::decrypt($asset->{$encrypted->db_column}))->toEqual('New Encrypted Text');
    });

    Asset::findMany($standard_id_array)->each(function (Asset $asset) use ($encrypted) {
        expect(Crypt::decrypt($asset->{$encrypted->db_column}))->toEqual('Original Encrypted Text');
    });
});
