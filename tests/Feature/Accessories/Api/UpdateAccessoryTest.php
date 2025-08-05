<?php

use App\Models\Accessory;
use App\Models\Category;
use App\Models\Company;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Models\User;
use Tests\Concerns\TestsFullMultipleCompaniesSupport;
use Tests\Concerns\TestsPermissionsRequirement;

test('requires permission', function () {
    $accessory = Accessory::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->patchJson(route('api.accessories.update', $accessory))
        ->assertForbidden();
});

test('adheres to full multiple companies support scoping', function () {
    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $accessoryA = Accessory::factory()->for($companyA)->create(['name' => 'A Name to Change']);
    $accessoryB = Accessory::factory()->for($companyB)->create(['name' => 'A Name to Change']);
    $accessoryC = Accessory::factory()->for($companyB)->create(['name' => 'A Name to Change']);

    $superuser = User::factory()->superuser()->create();
    $userInCompanyA = $companyA->users()->save(User::factory()->editAccessories()->make());
    $userInCompanyB = $companyB->users()->save(User::factory()->editAccessories()->make());

    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAsForApi($userInCompanyA)
        ->patchJson(route('api.accessories.update', $accessoryB), ['name' => 'New Name'])
        ->assertStatusMessageIs('error');

    $this->actingAsForApi($userInCompanyB)
        ->patchJson(route('api.accessories.update', $accessoryA), ['name' => 'New Name'])
        ->assertStatusMessageIs('error');

    $this->actingAsForApi($superuser)
        ->patchJson(route('api.accessories.update', $accessoryC), ['name' => 'New Name'])
        ->assertOk();

    expect($accessoryA->fresh()->name)->toEqual('A Name to Change');
    expect($accessoryB->fresh()->name)->toEqual('A Name to Change');
    expect($accessoryC->fresh()->name)->toEqual('New Name');
});

test('can update accessory via patch', function () {
    [$categoryA, $categoryB] = Category::factory()->count(2)->create();
    [$companyA, $companyB] = Company::factory()->count(2)->create();
    [$locationA, $locationB] = Location::factory()->count(2)->create();
    [$manufacturerA, $manufacturerB] = Manufacturer::factory()->count(2)->create();
    [$supplierA, $supplierB] = Supplier::factory()->count(2)->create();

    $accessory = Accessory::factory()->create([
        'name' => 'A Name to Change',
        'qty' => 5,
        'order_number' => 'A12345',
        'purchase_cost' => 99.99,
        'model_number' => 'ABC098',
        'category_id' => $categoryA->id,
        'company_id' => $companyA->id,
        'location_id' => $locationA->id,
        'manufacturer_id' => $manufacturerA->id,
        'supplier_id' => $supplierA->id,
    ]);

    $this->actingAsForApi(User::factory()->editAccessories()->create())
        ->patchJson(route('api.accessories.update', $accessory), [
            'name' => 'A New Name',
            'qty' => 10,
            'order_number' => 'B54321',
            'purchase_cost' => 199.99,
            'model_number' => 'XYZ123',
            'category_id' => $categoryB->id,
            'company_id' => $companyB->id,
            'location_id' => $locationB->id,
            'manufacturer_id' => $manufacturerB->id,
            'supplier_id' => $supplierB->id,
        ])
        ->assertOk();

    $accessory = $accessory->fresh();
    expect($accessory->name)->toEqual('A New Name');
    expect($accessory->qty)->toEqual(10);
    expect($accessory->order_number)->toEqual('B54321');
    expect($accessory->purchase_cost)->toEqual(199.99);
    expect($accessory->model_number)->toEqual('XYZ123');
    expect($accessory->category_id)->toEqual($categoryB->id);
    expect($accessory->company_id)->toEqual($companyB->id);
    expect($accessory->location_id)->toEqual($locationB->id);
    expect($accessory->manufacturer_id)->toEqual($manufacturerB->id);
    expect($accessory->supplier_id)->toEqual($supplierB->id);
});
