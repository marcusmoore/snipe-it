<?php

use App\Models\Company;
use App\Models\Location;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('viewing location index requires authentication', function () {
    $this->getJson(route('api.locations.index'))->assertRedirect();
});

test('viewing location index requires permission', function () {
    $this->actingAsForApi(User::factory()->create())
        ->getJson(route('api.locations.index'))
        ->assertForbidden();
});

test('location index returns expected locations', function () {
    Location::factory()->count(3)->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(
            route('api.locations.index', [
                'sort' => 'name',
                'order' => 'asc',
                'offset' => '0',
                'limit' => '20',
            ]))
        ->assertOk()
        ->assertJsonStructure([
            'total',
            'rows',
        ])
        ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
});
