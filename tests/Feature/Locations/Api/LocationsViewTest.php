<?php

use App\Models\Location;
use App\Models\Asset;
use App\Models\User;

test('viewing location requires permission', function () {
    $location = Location::factory()->create();
    $this->actingAsForApi(User::factory()->create())
        ->getJson(route('api.locations.show', $location->id))
        ->assertForbidden();
});

test('viewing location asset index requires permission', function () {
    $location = Location::factory()->create();
    $this->actingAsForApi(User::factory()->create())
        ->getJson(route('api.locations.viewassets', $location->id))
        ->assertForbidden();
});

test('viewing location asset index', function () {
    $location = Location::factory()->create();
    Asset::factory()->count(3)->create(['location_id' => $location->id]);

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(route('api.locations.viewassets', $location->id))
        ->assertOk()
        ->assertJsonStructure([
            'total',
            'rows',
        ])
        ->assertJson([
            'total' => 3,
        ]);
});

test('viewing assigned location asset index', function () {
    $location = Location::factory()->create();
    Asset::factory()->count(3)->assignedToLocation($location)->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(route('api.locations.assigned_assets', $location->id))
        ->assertOk()
        ->assertJsonStructure([
            'total',
            'rows',
        ])
        ->assertJson([
            'total' => 3,
        ]);
});
