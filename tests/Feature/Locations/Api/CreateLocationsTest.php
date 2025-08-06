<?php

use App\Models\Location;
use App\Models\User;

test('requires permission to create location', function () {
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.departments.store'))
        ->assertForbidden();
});

test('can create location', function () {
    $response = $this->actingAsForApi(User::factory()->superuser()->create())
        ->postJson(route('api.locations.store'), [
            'name' => 'Test Location',
            'notes' => 'Test Note',
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->assertStatus(200)
        ->json();

    expect(Location::where('name', 'Test Location')->exists())->toBeTrue();

    $department = Location::find($response['payload']['id']);
    expect($department->name)->toEqual('Test Location');
    expect($department->notes)->toEqual('Test Note');
});

test('cannot create new locations with the same name', function () {
    $location = Location::factory()->create();
    $location2 = Location::factory()->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.locations.update', $location2), [
            'name' => $location->name,
        ])
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->assertStatus(200)
        ->json();
});

test('user cannot create locations that are their own parent', function () {
    $location = Location::factory()->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.locations.update', $location), [
            'parent_id' => $location->id,
        ])
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->assertJson([
            'messages' => [
                'parent_id'    => ['The parent id must not create a circular reference.'],
            ],
        ])
        ->json();
});
