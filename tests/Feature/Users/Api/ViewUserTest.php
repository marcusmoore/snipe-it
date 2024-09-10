<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Passport\Passport;

test('can return user', function () {
    $user = User::factory()->create();

    $this->actingAsForApi(User::factory()->viewUsers()->create())
        ->getJson(route('api.users.show', $user))
        ->assertOk();
});
