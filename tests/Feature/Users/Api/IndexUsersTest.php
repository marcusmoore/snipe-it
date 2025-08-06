<?php

use App\Models\Location;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('requires permission', function () {
    $this->actingAsForApi(User::factory()->create())
        ->getJson(route('api.users.index'))
        ->assertForbidden();
});

test('returns managed users count correctly', function () {
    $manager = User::factory()->create(['first_name' => 'Manages Users']);
    User::factory()->create(['first_name' => 'Does Not Manage Users']);

    User::factory()->create(['manager_id' => $manager->id]);
    User::factory()->create(['manager_id' => $manager->id]);

    $response = $this->actingAsForApi(User::factory()->viewUsers()->create())
        ->getJson(route('api.users.index', [
            'manages_users_count' => 2,
        ]))
        ->assertOk();

    $response->assertJson(function (AssertableJson $json) {
        $json->has('rows', 1)
            ->where('rows.0.first_name', 'Manages Users')
            ->etc();
    });
});

test('returns managed locations count correctly', function () {
    $manager = User::factory()->create(['first_name' => 'Manages Locations']);
    User::factory()->create(['first_name' => 'Does Not Manage Locations']);

    Location::factory()->create(['manager_id' => $manager->id]);
    Location::factory()->create(['manager_id' => $manager->id]);

    $response = $this->actingAsForApi(User::factory()->viewUsers()->create())
        ->getJson(route('api.users.index', [
            'manages_locations_count' => 2,
        ]))
        ->assertOk();

    $response->assertJson(function (AssertableJson $json) {
        $json->has('rows', 1)
            ->where('rows.0.first_name', 'Manages Locations')
            ->etc();
    });
});
