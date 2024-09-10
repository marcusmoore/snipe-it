<?php

use App\Models\Location;
use App\Models\User;

test('requires permission to edit location', function () {
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.locations.store', Location::factory()->create()))
        ->assertForbidden();
});

test('can update location via patch', function () {
    $location = Location::factory()->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.locations.update', $location), [
            'name' => 'Test Location',
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->assertStatus(200)
        ->json();

    $location->refresh();
    expect($location->name)->toEqual('Test Location', 'Name was not updated');
});
