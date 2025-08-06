<?php

use App\Models\User;

test('user modal renders', function () {
    $admin = User::factory()->createUsers()->create();
    $response = $this->actingAs($admin)
        ->get('modals/user')
        ->assertOk();

    $response->assertStatus(200);
    $response->assertDontSee($admin->first_name);
    $response->assertDontSee($admin->last_name);
    $response->assertDontSee($admin->email);
});

test('department modal renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get('modals/model')
        ->assertOk();
});

test('status label modal renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get('modals/statuslabel')
        ->assertOk();
});

test('location modal renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get('modals/location')
        ->assertOk();
});

test('category modal renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get('modals/category')
        ->assertOk();
});

test('manufacturer modal renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get('modals/manufacturer')
        ->assertOk();
});

test('supplier modal renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get('modals/supplier')
        ->assertOk();
});
