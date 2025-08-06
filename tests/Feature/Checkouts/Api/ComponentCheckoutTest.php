<?php

use App\Models\Asset;
use App\Models\Company;
use App\Models\Component;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;

test('requires permission', function () {
    $component = Component::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.components.checkout', $component->id))
        ->assertForbidden();
});

test('cannot checkout non existent component', function () {
    $this->actingAsForApi(User::factory()->checkoutComponents()->create())
        ->postJson(route('api.components.checkout', 1000))
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->assertMessagesAre('Component does not exist.');
});

test('checking out component requires valid fields', function () {
    $component = Component::factory()->create();

    $this->actingAsForApi(User::factory()->checkoutComponents()->create())
        ->postJson(route('api.components.checkout', $component->id), [
            //
        ])
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->assertPayloadContains('assigned_to')
        ->assertPayloadContains('assigned_qty');
});

test('cannot checkout component if requested amount is more than component quantity', function () {
    $asset = Asset::factory()->create();
    $component = Component::factory()->create(['qty' => 2]);

    $this->actingAsForApi(User::factory()->checkoutComponents()->create())
        ->postJson(route('api.components.checkout', $component->id), [
            'assigned_to' => $asset->id,
            'assigned_qty' => 3,
        ])
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->assertMessagesAre(trans('admin/components/message.checkout.unavailable', ['remaining' => 2, 'requested' => 3]));
});

test('cannot checkout component if requested amount is more than what is remaining', function () {
    $asset = Asset::factory()->create();
    $component = Component::factory()->create(['qty' => 2]);
    $component->assets()->attach($component->id, [
        'component_id' => $component->id,
        'created_at' => Carbon::now(),
        'assigned_qty' => 1,
        'asset_id' => $asset->id,
    ]);

    $this->actingAsForApi(User::factory()->checkoutComponents()->create())
        ->postJson(route('api.components.checkout', $component->id), [
            'assigned_to' => $asset->id,
            'assigned_qty' => 3,
        ])
        ->assertOk()
        ->assertStatusMessageIs('error');
});

test('can checkout component', function () {
    $user = User::factory()->checkoutComponents()->create();
    $asset = Asset::factory()->create();
    $component = Component::factory()->create();

    $this->actingAsForApi($user)
        ->postJson(route('api.components.checkout', $component->id), [
            'assigned_to' => $asset->id,
            'assigned_qty' => 1,
        ])
        ->assertOk()
        ->assertStatusMessageIs('success');

    expect($component->assets->first()->is($asset))->toBeTrue();
    $this->assertHasTheseActionLogs($component, ['create', 'checkout']);
});

test('component checkout is logged', function () {
    $user = User::factory()->checkoutComponents()->create();
    $location = Location::factory()->create();
    $asset = Asset::factory()->create(['location_id' => $location->id]);
    $component = Component::factory()->create();

    $this->actingAsForApi($user)
        ->postJson(route('api.components.checkout', $component->id), [
            'assigned_to' => $asset->id,
            'assigned_qty' => 1,
        ]);

    $this->assertDatabaseHas('action_logs', [
        'created_by' => $user->id,
        'action_type' => 'checkout',
        'target_id' => $asset->id,
        'target_type' => Asset::class,
        'location_id' => $location->id,
        'item_type' => Component::class,
        'item_id' => $component->id,
    ]);
});

test('adheres to full multiple companies support scoping', function () {
    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $userForCompanyA = User::factory()->for($companyA)->create();
    $assetForCompanyB = Asset::factory()->for($companyB)->create();
    $componentForCompanyB = Component::factory()->for($companyB)->create();

    $this->actingAsForApi($userForCompanyA)
        ->postJson(route('api.components.checkout', $componentForCompanyB->id), [
            'assigned_to' => $assetForCompanyB->id,
            'assigned_qty' => 1,
        ])
        ->assertForbidden();
});
