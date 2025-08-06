<?php

use App\Models\Accessory;
use App\Models\Manufacturer;
use App\Models\Location;
use App\Models\Category;
use App\Models\Company;

test('an accessory belongs to acompany', function () {
    $accessory = Accessory::factory()
    ->create(
        [
            'company_id' => 
                Company::factory()->create()->id]);
    expect($accessory->company)->toBeInstanceOf(Company::class);
});

test('an accessory has alocation', function () {
    $accessory = Accessory::factory()
        ->create(
            [
                'location_id' => Location::factory()->create()->id
            ]);
    expect($accessory->location)->toBeInstanceOf(Location::class);
});

test('an accessory belongs to acategory', function () {
    $accessory = Accessory::factory()->appleBtKeyboard()
        ->create(
            [
                'category_id' => 
                    Category::factory()->create(
                        [
                            'category_type' => 'accessory'
                        ]
            )->id]);
    expect($accessory->category)->toBeInstanceOf(Category::class);
    expect($accessory->category->category_type)->toEqual('accessory');
});

test('an accessory has amanufacturer', function () {
    $accessory = Accessory::factory()->appleBtKeyboard()->create(
        [
            'category_id' => Category::factory()->create(),
            'manufacturer_id' => Manufacturer::factory()->apple()->create()
        ]);
    expect($accessory->manufacturer)->toBeInstanceOf(Manufacturer::class);
});
