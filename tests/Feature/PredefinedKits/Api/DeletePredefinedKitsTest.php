<?php

use App\Models\PredefinedKit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('requires permission', function () {
    $predefinedKit = PredefinedKit::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->deleteJson(route('api.kits.destroy', $predefinedKit))
        ->assertForbidden();

    $this->assertDatabaseHas('kits', ['id' => $predefinedKit->id]);
});

test('can delete predefined kits', function () {
    $predefinedKit = PredefinedKit::factory()->create();

    $this->actingAsForApi(User::factory()->deletePredefinedKits()->create())
        ->deleteJson(route('api.kits.destroy', $predefinedKit))
        ->assertOk()
        ->assertStatusMessageIs('success');

    $this->assertDatabaseMissing('kits', ['id' => $predefinedKit->id]);
});

test('associated data detached when predefined kit deleted', function () {
    $predefinedKit = PredefinedKit::factory()
        ->hasAccessories()
        ->hasConsumables()
        ->hasLicenses()
        ->hasModels()
        ->create();

    expect($predefinedKit->accessories->count())->toBeGreaterThan(0, 'Precondition failed: PredefinedKit has no accessories');
    expect($predefinedKit->consumables->count())->toBeGreaterThan(0, 'Precondition failed: PredefinedKit has no consumables');
    expect($predefinedKit->licenses->count())->toBeGreaterThan(0, 'Precondition failed: PredefinedKit has no licenses');
    expect($predefinedKit->models->count())->toBeGreaterThan(0, 'Precondition failed: PredefinedKit has no models');

    $this->actingAsForApi(User::factory()->deletePredefinedKits()->create())
        ->deleteJson(route('api.kits.destroy', $predefinedKit))
        ->assertStatusMessageIs('success');

    expect(DB::table('kits_accessories')->where('kit_id', $predefinedKit->id)->count())->toEqual(0);
    expect(DB::table('kits_consumables')->where('kit_id', $predefinedKit->id)->count())->toEqual(0);
    expect(DB::table('kits_licenses')->where('kit_id', $predefinedKit->id)->count())->toEqual(0);
    expect(DB::table('kits_models')->where('kit_id', $predefinedKit->id)->count())->toEqual(0);
});
