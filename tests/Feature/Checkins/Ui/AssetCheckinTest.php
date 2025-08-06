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
    $this->actingAs(User::factory()->create())
        ->post(route('hardware.checkin.store', [Asset::factory()->assignedToUser()->create()]))
        ->assertForbidden();
});

test('cannot check in asset that is not checked out', function () {
    $this->actingAs(User::factory()->checkinAssets()->create())
        ->post(route('hardware.checkin.store', [Asset::factory()->create()]))
        ->assertStatus(302)
        ->assertSessionHas('error')
        ->assertRedirect(route('hardware.index'));
});

test('cannot store asset checkin that is not checked out', function () {
    $this->actingAs(User::factory()->checkinAssets()->create())
        ->get(route('hardware.checkin.store', [Asset::factory()->create()]))
        ->assertStatus(302)
        ->assertSessionHas('error')
        ->assertRedirect(route('hardware.index'));
});

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('hardware.checkin.create', Asset::factory()->assignedToUser()->create()))
        ->assertOk();
});

test('asset can be checked in', function () {
    Event::fake([CheckoutableCheckedIn::class]);

    $user = User::factory()->create();
    $location = Location::factory()->create();
    $status = Statuslabel::first() ?? Statuslabel::factory()->create();
    $asset = Asset::factory()->assignedToUser($user)->create([
        'expected_checkin' => now()->addDay(),
        'last_checkin' => null,
        'accepted' => 'accepted',
    ]);

    expect($asset->assignedTo->is($user))->toBeTrue();

    $currentTimestamp = now();

    $this->actingAs(User::factory()->checkinAssets()->create())
        ->post(
            route('hardware.checkin.store', [$asset]),
            [
                'name' => 'Changed Name',
                'status_id' => $status->id,
                'location_id' => $location->id,
            ],
        );

    expect($asset->refresh()->assignedTo)->toBeNull();
    expect($asset->expected_checkin)->toBeNull();
    expect($asset->last_checkin)->not->toBeNull();
    expect($asset->assignedTo)->toBeNull();
    expect($asset->assigned_type)->toBeNull();
    expect($asset->accepted)->toBeNull();
    expect($asset->name)->toEqual('Changed Name');
    expect($asset->status_id)->toEqual($status->id);
    expect($asset->location()->is($location))->toBeTrue();

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

    $this->actingAs(User::factory()->checkinAssets()->create())
        ->post(route('hardware.checkin.store', [$asset]));

    expect($asset->refresh()->location()->is($rtdLocation))->toBeTrue();
    $this->assertHasTheseActionLogs($asset, ['create', 'checkin from']);
});

test('default location can be updated upon checkin', function () {
    $location = Location::factory()->create();
    $asset = Asset::factory()->assignedToUser()->create();

    $this->actingAs(User::factory()->checkinAssets()->create())
        ->post(route('hardware.checkin.store', [$asset]), [
            'location_id' => $location->id,
            'update_default_location' => 0
        ]);

    expect($asset->refresh()->defaultLoc()->is($location))->toBeTrue();
});

test('assets license seats are cleared upon checkin', function () {
    $asset = Asset::factory()->assignedToUser()->create();
    LicenseSeat::factory()->assignedToUser()->for($asset)->create();

    expect($asset->licenseseats->first()->assigned_to)->not->toBeNull();

    $this->actingAs(User::factory()->checkinAssets()->create())
        ->post(route('hardware.checkin.store', [$asset]));

    expect($asset->refresh()->licenseseats->first()->assigned_to)->toBeNull();
});

test('legacy location values set to zero are updated', function () {
    $asset = Asset::factory()->canBeInvalidUponCreation()->assignedToUser()->create([
        'rtd_location_id' => 0,
        'location_id' => 0,
    ]);

    $this->actingAs(User::factory()->checkinAssets()->create())
        ->post(route('hardware.checkin.store', [$asset]));

    expect($asset->refresh()->rtd_location_id)->toBeNull();
    expect($asset->rtd_location_id)->toEqual($asset->location_id);
});

test('pending checkout acceptances are cleared upon checkin', function () {
    $asset = Asset::factory()->assignedToUser()->create();

    $acceptance = CheckoutAcceptance::factory()->for($asset, 'checkoutable')->pending()->create();

    $this->actingAs(User::factory()->checkinAssets()->create())
        ->post(route('hardware.checkin.store', [$asset]));

    expect($acceptance->exists())->toBeFalse('Acceptance was not deleted');
});

test('checkin time and action log note can be set', function () {
    Event::fake([CheckoutableCheckedIn::class]);

    $this->actingAs(User::factory()->checkinAssets()->create())
        ->post(route(
            'hardware.checkin.store', [Asset::factory()->assignedToUser()->create()]
        ), [
            'checkin_at' => '2023-01-02',
            'note' => 'hello'
        ]);

    Event::assertDispatched(function (CheckoutableCheckedIn $event) {
        return $event->action_date === '2023-01-02' && $event->note === 'hello';
    }, 1);
});

test('asset checkin page is redirected if model is invalid', function () {
    $asset = Asset::factory()->assignedToUser()->create();
    $asset->model_id = 0;
    $asset->forceSave();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('hardware.checkin.create', [$asset]))
        ->assertStatus(302)
        ->assertSessionHas('error')
        ->assertRedirect(route('hardware.show', $asset));
});

test('asset checkin page post is redirected if model is invalid', function () {
    $asset = Asset::factory()->assignedToUser()->create();
    $asset->model_id = 0;
    $asset->forceSave();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('hardware.checkin.store', $asset))
        ->assertStatus(302)
        ->assertSessionHas('error')
        ->assertRedirect(route('hardware.show', $asset));
});

test('asset checkin page post is redirected if redirect selection is index', function () {
    $asset = Asset::factory()->assignedToUser()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('hardware.index'))
        ->post(route('hardware.checkin.store', $asset), [
            'redirect_option' => 'index',
        ])
        ->assertStatus(302)
        ->assertRedirect(route('hardware.index'));
});

test('asset checkin page post is redirected if redirect selection is item', function () {
    $asset = Asset::factory()->assignedToUser()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('hardware.index'))
        ->post(route('hardware.checkin.store', $asset), [
            'redirect_option' => 'item',
        ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('hardware.show', $asset));
});
