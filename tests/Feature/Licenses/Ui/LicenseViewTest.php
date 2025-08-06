<?php

use App\Models\License;
use App\Models\Depreciation;
use App\Models\User;

test('permission required to view license', function () {
    $license = License::factory()->create();
    $this->actingAs(User::factory()->create())
        ->get(route('licenses.show', $license))
        ->assertForbidden();
});

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('licenses.show', License::factory()->create()->id))
        ->assertOk();
});

test('license with purchase date depreciates correctly', function () {
    $depreciation = Depreciation::factory()->create(['months' => 12]);
    $license = License::factory()->create(['depreciation_id' => $depreciation->id, 'purchase_date' => '2020-01-01']);
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('licenses.show', $license))
        ->assertOk()
        ->assertSee([
            '2021-01-01'
        ], false);
});
