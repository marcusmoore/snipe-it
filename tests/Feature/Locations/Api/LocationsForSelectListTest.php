<?php

use App\Models\Location;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('getting location list requires proper permission', function () {
    $this->actingAsForApi(User::factory()->create())
        ->getJson(route('api.locations.selectlist'))
        ->assertForbidden();
});

test('locations returned', function () {
    Location::factory()->create();

    // see the where the "view.selectlists" is defined in the AuthServiceProvider
    // for info on why "createUsers()" is used here.
    $this->actingAsForApi(User::factory()->createUsers()->create())
        ->getJson(route('api.locations.selectlist'))
        ->assertOk()
        ->assertJsonStructure([
            'results',
            'pagination',
            'total_count',
            'page',
            'page_count',
        ])
        ->assertJson(fn(AssertableJson $json) => $json->has('results', 1)->etc());
});

test('locations are returned when user is updating their profile and has permission to update location', function () {
    $this->actingAsForApi(User::factory()->canEditOwnLocation()->create())
        ->withHeader('referer', route('profile'))
        ->getJson(route('api.locations.selectlist'))
        ->assertOk();
});
