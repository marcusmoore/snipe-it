<?php

use App\Models\Asset;
use App\Models\Location;
use App\Models\User;

test('requires permission', function () {
    $location = Location::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->deleteJson(route('api.locations.destroy', $location))
        ->assertForbidden();

    $this->assertNotSoftDeleted($location);
});

test('error returned via api if location does not exist', function () {
    $this->actingAsForApi(User::factory()->superuser()->create())
        ->deleteJson(route('api.users.destroy', 'invalid-id'))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('error returned via api if location is already deleted', function () {
    $location = Location::factory()->deletedLocation()->create();
    $this->actingAsForApi(User::factory()->superuser()->create())
        ->deleteJson(route('api.locations.destroy', $location->id))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('disallow location deletion via api if still has people', function () {
    $location = Location::factory()->create();
    User::factory()->count(5)->create(['location_id' => $location->id]);

    expect($location->isDeletable())->toBeFalse();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->deleteJson(route('api.locations.destroy', $location->id))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('disallow user deletion via api if still has child locations', function () {
    $parent = Location::factory()->create();
    Location::factory()->count(5)->create(['parent_id' => $parent->id]);
    expect($parent->isDeletable())->toBeFalse();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->deleteJson(route('api.locations.destroy', $parent->id))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('disallow user deletion via api if still has assets assigned', function () {
    $location = Location::factory()->create();
    Asset::factory()->count(5)->assignedToLocation($location)->create();

    expect($location->isDeletable())->toBeFalse();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->deleteJson(route('api.locations.destroy', $location->id))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('disallow user deletion via api if still has assets as location', function () {
    $location = Location::factory()->create();
    Asset::factory()->count(5)->create(['location_id' => $location->id]);

    expect($location->isDeletable())->toBeFalse();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->deleteJson(route('api.locations.destroy', $location->id))
        ->assertOk()
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->json();
});

test('can delete location', function () {
    $location = Location::factory()->create();

    $this->actingAsForApi(User::factory()->deleteLocations()->create())
        ->deleteJson(route('api.locations.destroy', $location->id))
        ->assertOk()
        ->assertStatusMessageIs('success');

    $this->assertSoftDeleted($location);
});
