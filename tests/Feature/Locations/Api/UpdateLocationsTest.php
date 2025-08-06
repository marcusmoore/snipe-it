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
            'name' => 'Test Updated Location',
            'notes' => 'Test Updated Note',
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->assertStatus(200)
        ->json();

    $location->refresh();
    expect($location->name)->toEqual('Test Updated Location', 'Name was not updated');
    expect($location->notes)->toEqual('Test Updated Note', 'Note was not updated');
});
