<?php

use App\Events\CheckoutableCheckedIn;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\LicenseSeat;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

test('checking in asset requires correct permission', function () {
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.asset.checkin', Asset::factory()->assignedToUser()->create()))
        ->assertForbidden();
});

test('cannot check in non existent asset', function () {
    $this->actingAsForApi(User::factory()->checkinAssets()->create())
        ->postJson(route('api.asset.checkin', ['id' => 'does-not-exist']))
        ->assertStatusMessageIs('error');
});

test('cannot check in asset that is not checked out', function () {
    $this->actingAsForApi(User::factory()->checkinAssets()->create())
        ->postJson(route('api.asset.checkin', Asset::factory()->create()->id))
        ->assertStatusMessageIs('error');
});

test('asset can be checked in', function () {
    Event::fake([CheckoutableCheckedIn::class]);

    $user = User::factory()->create();
    $location = Location::factory()->create();
    $status = Statuslabel::factory()->create();
    $asset = Asset::factory()->assignedToUser($user)->create([
        'expected_checkin' => now()->addDay(),
        'last_checkin' => null,
        'accepted' => 'accepted',
    ]);

    expect($asset->assignedTo->is($user))->toBeTrue();

    $currentTimestamp = now();

    $this->actingAsForApi(User::factory()->checkinAssets()->create())
        ->postJson(route('api.asset.checkin', $asset), [
            'name' => 'Changed Name',
            'status_id' => $status->id,
            'location_id' => $location->id,
        ])
        ->assertOk();

    expect($asset->refresh()->assignedTo)->toBeNull();
    expect($asset->expected_checkin)->toBeNull();
    expect($asset->assignedTo)->toBeNull();
    expect($asset->assigned_type)->toBeNull();
    expect($asset->accepted)->toBeNull();
    expect($asset->name)->toEqual('Changed Name');
    expect($asset->status_id)->toEqual($status->id);
    expect($asset->location()->is($location))->toBeTrue();
    $this->assertHasTheseActionLogs($asset, ['create'/*, 'checkout', 'checkin from'*/]);

    //TODO - the Event::fake() is probably getting in the way here
    Event::assertDispatched(function (CheckoutableCheckedIn $event) use ($currentTimestamp) {
        // this could be better mocked but is ok for now.
        return (int) Carbon::parse($event->action_date)->diffInSeconds($currentTimestamp, true) < 2;
    }, 1);
});

test('location is set to rtdlocation by default upon checkin', function () {
    $rtdLocation = Location::factory()->create();
    $asset = Asset::factory()->assignedToUser()->create([
        'location_id' => Location::factory()->create()->id,
        'rtd_location_id' => $rtdLocation->id,
    ]);

    $this->actingAsForApi(User::factory()->checkinAssets()->create())
        ->postJson(route('api.asset.checkin', $asset->id));

    expect($asset->refresh()->location()->is($rtdLocation))->toBeTrue();
    $this->assertHasTheseActionLogs($asset, ['create', /*'checkout',*/ 'checkin from']);
    //FIXME?
});

test('default location can be updated upon checkin', function () {
    $location = Location::factory()->create();
    $asset = Asset::factory()->assignedToUser()->create();

    $this->actingAsForApi(User::factory()->checkinAssets()->create())
        ->postJson(route('api.asset.checkin', $asset), [
            'location_id' => $location->id,
            'update_default_location' => true,
        ]);

    expect($asset->refresh()->defaultLoc()->is($location))->toBeTrue();
    $this->assertHasTheseActionLogs($asset, ['create', /*'checkout',*/ 'checkin from']);
    //FIXME?
});

test('assets license seats are cleared upon checkin', function () {
    $asset = Asset::factory()->assignedToUser()->create();
    LicenseSeat::factory()->assignedToUser()->for($asset)->create();

    expect($asset->licenseseats->first()->assigned_to)->not->toBeNull();

    $this->actingAsForApi(User::factory()->checkinAssets()->create())
        ->postJson(route('api.asset.checkin', $asset));

    expect($asset->refresh()->licenseseats->first()->assigned_to)->toBeNull();
});

test('legacy location values set to zero are updated', function () {
    $asset = Asset::factory()->canBeInvalidUponCreation()->assignedToUser()->create([
        'rtd_location_id' => 0,
        'location_id' => 0,
    ]);

    $this->actingAsForApi(User::factory()->checkinAssets()->create())
        ->postJson(route('api.asset.checkin', $asset));

    expect($asset->refresh()->rtd_location_id)->toBeNull();
    expect($asset->rtd_location_id)->toEqual($asset->location_id);
});

test('pending checkout acceptances are cleared upon checkin', function () {
    $asset = Asset::factory()->assignedToUser()->create();

    $acceptance = CheckoutAcceptance::factory()->for($asset, 'checkoutable')->pending()->create();

    $this->actingAsForApi(User::factory()->checkinAssets()->create())
        ->postJson(route('api.asset.checkin', $asset));

    expect($acceptance->exists())->toBeFalse('Acceptance was not deleted');
});

test('checkin time and action log note can be set', function () {
    Event::fake();

    $this->actingAsForApi(User::factory()->checkinAssets()->create())
        ->postJson(route('api.asset.checkin', Asset::factory()->assignedToUser()->create()), [
            // time is appended to the provided date in controller
            'checkin_at' => '2023-01-02',
            'note' => 'hi there',
        ]);

    Event::assertDispatched(function (CheckoutableCheckedIn $event) {
        return Carbon::parse('2023-01-02')->isSameDay(Carbon::parse($event->action_date))
            && $event->note === 'hi there';
    }, 1);
});
