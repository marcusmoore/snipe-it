<?php

use App\Models\User;

test('rate limit', function () {
    config(['app.api_throttle_per_minute' => 10]);
    $this->actingAsForApi(User::factory()->create())
        ->getJson(route('api.users.me'))
        ->assertOk()
        ->assertHeader('X-Ratelimit-Limit', config('app.api_throttle_per_minute'))
        ->assertHeader('X-Ratelimit-Remaining', 9);
});

test('rate limit decreases remaining', function () {
    config(['app.api_throttle_per_minute' => 5]);
    $expected_remaining = (config('app.api_throttle_per_minute') - 1);
    $admin = User::factory()->create();

    for ($x = 0; $x < 5; $x++) {

        $this->actingAsForApi($admin)
            ->getJson(route('api.users.me'))
            ->assertOk()
            ->assertHeader('X-Ratelimit-Remaining', $expected_remaining--);

    }

    $this->actingAsForApi($admin)
        ->getJson(route('api.users.me'))
        ->assertStatus(429)
        ->assertHeader('Retry-After', 60);
});

test('rate limit decreases remaining over sixty', function () {
    config(['app.api_throttle_per_minute' => 80]);
    $expected_remaining = (config('app.api_throttle_per_minute') - 1);
    $admin = User::factory()->create();

    for ($x = 0; $x < 5; $x++) {

        $this->actingAsForApi($admin)
            ->getJson(route('api.users.me'))
            ->assertOk()
            ->assertHeader('X-Ratelimit-Remaining', $expected_remaining--);

    }

    $this->actingAsForApi($admin)
        ->getJson(route('api.users.me'))
        ->assertStatus(200)
        ->assertHeader('Retry-After', 60);
});
