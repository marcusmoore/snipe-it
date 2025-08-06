<?php

use App\Models\Accessory;
use App\Models\Company;
use App\Models\User;

test('requires permission', function () {
    $accessory = Accessory::factory()->checkedOutToUser()->create();
    $accessoryCheckout = $accessory->checkouts->first();

    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.accessories.checkin', $accessoryCheckout))
        ->assertForbidden();
});

test('adheres to full multiple companies support scoping', function () {
    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $superUser = $companyA->users()->save(User::factory()->superuser()->make());
    $userInCompanyA = User::factory()->for($companyA)->checkinAccessories()->create();
    $accessoryForCompanyB = Accessory::factory()->for($companyB)->checkedOutToUser()->create();
    $anotherAccessoryForCompanyB = Accessory::factory()->for($companyB)->checkedOutToUser()->create();

    expect($accessoryForCompanyB->checkouts->count())->toEqual(1);
    expect($anotherAccessoryForCompanyB->checkouts->count())->toEqual(1);

    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAsForApi($userInCompanyA)
        ->postJson(route('api.accessories.checkin', $accessoryForCompanyB->checkouts->first()))
        ->assertForbidden();

    $this->actingAsForApi($superUser)
        ->postJson(route('api.accessories.checkin', $anotherAccessoryForCompanyB->checkouts->first()))
        ->assertStatusMessageIs('success');

    expect($accessoryForCompanyB->fresh()->checkouts->count())->toEqual(1, 'Accessory should not be checked in');
    expect($anotherAccessoryForCompanyB->fresh()->checkouts->count())->toEqual(0, 'Accessory should be checked in');
    $this->assertHasTheseActionLogs($anotherAccessoryForCompanyB, ['create', 'checkin from']);
});

test('can checkin accessory', function () {
    $accessory = Accessory::factory()->checkedOutToUser()->create();

    expect($accessory->checkouts->count())->toEqual(1);

    $accessoryCheckout = $accessory->checkouts->first();

    $this->actingAsForApi(User::factory()->checkinAccessories()->create())
        ->postJson(route('api.accessories.checkin', $accessoryCheckout))
        ->assertStatusMessageIs('success');

    expect($accessory->fresh()->checkouts->count())->toEqual(0, 'Accessory should be checked in');
    $this->assertHasTheseActionLogs($accessory, ['create'/*, 'checkout'*/, 'checkin from']);
    // TODO - should be the 3 events!
});

test('checkin is logged', function () {
    $user = User::factory()->create();
    $actor = User::factory()->checkinAccessories()->create();

    $accessory = Accessory::factory()->checkedOutToUser($user)->create();
    $accessoryCheckout = $accessory->checkouts->first();

    $this->actingAsForApi($actor)
        ->postJson(route('api.accessories.checkin', $accessoryCheckout))
        ->assertStatusMessageIs('success');

    $this->assertDatabaseHas('action_logs', [
        'created_by' => $actor->id,
        'action_type' => 'checkin from',
        'target_id' => $user->id,
        'target_type' => User::class,
        'item_id' => $accessory->id,
        'item_type' => Accessory::class,
    ]);
});
