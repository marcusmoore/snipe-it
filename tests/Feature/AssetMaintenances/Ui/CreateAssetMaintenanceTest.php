<?php

use App\Models\Asset;
use App\Models\Supplier;
use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('maintenances.create'))
        ->assertOk();
});

test('can create asset maintenance', function () {
    $actor = User::factory()->superuser()->create();

    $asset = Asset::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($actor)
        ->followingRedirects()
        ->post(route('maintenances.store'), [
            'title' => 'Test Maintenance',
            'selected_assets' => [$asset->id],
            'supplier_id' => $supplier->id,
            'asset_maintenance_type' => 'Maintenance',
            'start_date' => '2021-01-01',
            'completion_date' => '2021-01-10',
            'is_warranty' => '1',
            'cost' => '100.00',
            'notes' => 'A note',
        ])
        ->assertOk();

    $this->assertDatabaseHas('asset_maintenances', [
        'asset_id' => $asset->id,
        'supplier_id' => $supplier->id,
        'asset_maintenance_type' => 'Maintenance',
        'title' => 'Test Maintenance',
        'is_warranty' => 1,
        'start_date' => '2021-01-01',
        'completion_date' => '2021-01-10',
        'asset_maintenance_time' => '9',
        'notes' => 'A note',
        'cost' => '100.00',
        'created_by' => $actor->id,
    ]);
});
