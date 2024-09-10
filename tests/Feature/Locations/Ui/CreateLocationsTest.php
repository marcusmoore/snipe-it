<?php

use App\Models\Location;
use App\Models\Company;
use App\Models\User;

test('permission required to create location', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('locations.store'), [
            'name' => 'Test Location',
            'company_id' => Company::factory()->create()->id
        ])
        ->assertForbidden();
});

test('user can create locations', function () {
    expect(Location::where('name', 'Test Location')->exists())->toBeFalse();

    $this->actingAs(User::factory()->superuser()->create())
        ->post(route('locations.store'), [
            'name' => 'Test Location',
            'company_id' => Company::factory()->create()->id
        ])
        ->assertRedirect(route('locations.index'));

    expect(Location::where('name', 'Test Location')->exists())->toBeTrue();
});

test('user cannot create locations with invalid parent', function () {
    expect(Location::where('name', 'Test Location')->exists())->toBeFalse();

    $this->actingAs(User::factory()->superuser()->create())
        ->from(route('locations.create'))
        ->post(route('locations.store'), [
            'name' => 'Test Location',
            'parent_id' => '100000000'
        ])
        ->assertRedirect(route('locations.create'));

    expect(Location::where('name', 'Test Location')->exists())->toBeFalse();
});
