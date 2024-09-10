<?php

use App\Models\Location;
use App\Models\User;

test('permission required to store location', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('locations.store'), [
            'name' => 'Test Location',
        ])
        ->assertStatus(403)
        ->assertForbidden();
});

test('user can edit locations', function () {
    $location = Location::factory()->create(['name' => 'Test Location']);
    expect(Location::where('name', 'Test Location')->exists())->toBeTrue();

    $response = $this->actingAs(User::factory()->superuser()->create())
        ->put(route('locations.update', ['location' => $location]), [
            'name' => 'Test Location Edited',
        ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('locations.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect(Location::where('name', 'Test Location Edited')->exists())->toBeTrue();
});

test('user cannot edit locations to make them their own parent', function () {
    $location = Location::factory()->create();

    $response = $this->actingAs(User::factory()->superuser()->create())
        ->from(route('locations.edit', ['location' => $location->id]))
        ->put(route('locations.update', ['location' => $location]), [
            'name' => 'Test Location',
            'parent_id' => $location->id,
        ])
        ->assertRedirect(route('locations.edit', ['location' => $location]));

    $this->followRedirects($response)->assertSee(trans('general.error'));
    expect(Location::where('name', 'Test Location')->exists())->toBeFalse();
});

test('user cannot edit locations with invalid parent', function () {
    $location = Location::factory()->create();
    $response = $this->actingAs(User::factory()->superuser()->create())
        ->from(route('locations.edit', ['location' => $location->id]))
        ->put(route('locations.update', ['location' => $location]), [
            'name' => 'Test Location',
            'parent_id' => '100000000'
        ])
        ->assertRedirect(route('locations.edit', ['location' => $location->id]));

    $this->followRedirects($response)->assertSee(trans('general.error'));
    expect(Location::where('name', 'Test Location')->exists())->toBeFalse();
});
