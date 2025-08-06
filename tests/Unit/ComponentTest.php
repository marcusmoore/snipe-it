<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\Company;
use App\Models\Component;
use App\Models\Location;
use App\Models\User;

test('acomponent belongs to acompany', function () {
    $component = Component::factory()
        ->create(
                [
                    'company_id' => Company::factory()->create()->id
                ]
            );
    expect($component->company)->toBeInstanceOf(Company::class);
});

test('acomponent has alocation', function () {
    $component = Component::factory()
        ->create(['location_id' => Location::factory()->create()->id]);
    expect($component->location)->toBeInstanceOf(Location::class);
});

test('acomponent belongs to acategory', function () {
    $component = Component::factory()->ramCrucial4()
        ->create(
            [
                'category_id' => 
                    Category::factory()->create(
                        [
                            'category_type' => 'component'
                        ]
            )->id]);
    expect($component->category)->toBeInstanceOf(Category::class);
    expect($component->category->category_type)->toEqual('component');
});

test('num checked out takes does not scope by company', function () {
    $this->settings->enableMultipleFullCompanySupport();

    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $componentForCompanyA = Component::factory()->for($companyA)->create(['qty' => 5]);
    $assetForCompanyB = Asset::factory()->for($companyB)->create();

    // Ideally, we shouldn't have a component attached to an
    // asset from a different company but alas...
    $componentForCompanyA->assets()->attach($componentForCompanyA->id, [
        'component_id' => $componentForCompanyA->id,
        'assigned_qty' => 4,
        'asset_id' => $assetForCompanyB->id,
    ]);

    $this->actingAs(User::factory()->superuser()->create());
    expect($componentForCompanyA->fresh()->numCheckedOut())->toEqual(4);

    $this->actingAs(User::factory()->admin()->create());
    expect($componentForCompanyA->fresh()->numCheckedOut())->toEqual(4);

    $this->actingAs(User::factory()->for($companyA)->create());
    expect($componentForCompanyA->fresh()->numCheckedOut())->toEqual(4);
});

test('num remaining takes company scoping into account', function () {
    $this->settings->enableMultipleFullCompanySupport();

    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $componentForCompanyA = Component::factory()->for($companyA)->create(['qty' => 5]);
    $assetForCompanyB = Asset::factory()->for($companyB)->create();

    // Ideally, we shouldn't have a component attached to an
    // asset from a different company but alas...
    $componentForCompanyA->assets()->attach($componentForCompanyA->id, [
        'component_id' => $componentForCompanyA->id,
        'assigned_qty' => 4,
        'asset_id' => $assetForCompanyB->id,
    ]);

    $this->actingAs(User::factory()->superuser()->create());
    expect($componentForCompanyA->fresh()->numRemaining())->toEqual(1);

    $this->actingAs(User::factory()->admin()->create());
    expect($componentForCompanyA->fresh()->numRemaining())->toEqual(1);

    $this->actingAs(User::factory()->for($companyA)->create());
    expect($componentForCompanyA->fresh()->numRemaining())->toEqual(1);
});
