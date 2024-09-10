<?php

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\User;

test('users without admin access are redirected', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('view-assets'));
});

test('counts are loaded correctly for admins', function () {
    Asset::factory()->count(2)->create();
    Accessory::factory()->count(2)->create();
    License::factory()->count(2)->create();
    Consumable::factory()->count(2)->create();
    Component::factory()->count(2)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('home'))
        ->assertViewIs('dashboard')
        ->assertViewHas('counts', function ($value) {
            $accessoryCount = Accessory::count();
            $assetCount = Asset::count();
            $componentCount = Component::count();
            $consumableCount = Consumable::count();
            $licenseCount = License::assetcount();
            $userCount = User::count();

            expect($accessoryCount)->toEqual($value['accessory'], 'Accessory count incorrect.');
            expect($assetCount)->toEqual($value['asset'], 'Asset count incorrect.');
            expect($licenseCount)->toEqual($value['license'], 'License count incorrect.');
            expect($consumableCount)->toEqual($value['consumable'], 'Consumable count incorrect.');
            expect($componentCount)->toEqual($value['component'], 'Component count incorrect.');
            expect($userCount)->toEqual($value['user'], 'User count incorrect.');
            expect($accessoryCount + $assetCount + $consumableCount + $licenseCount)->toEqual($value['grand_total'], 'Grand total count incorrect.');

            return true;
        });
});
