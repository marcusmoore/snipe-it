<?php

use App\Events\CheckoutableCheckedIn;
use App\Models\Asset;
use App\Models\LicenseSeat;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('checking in license requires correct permission', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('licenses.checkin.save', [
            'licenseId' => LicenseSeat::factory()->assignedToUser()->create()->id,
        ]))
        ->assertForbidden();
});

test('cannot checkin non reassignable license', function () {
    $licenseSeat = LicenseSeat::factory()
        ->notReassignable()
        ->assignedToUser()
        ->create();

    $this->actingAs(User::factory()->checkoutLicenses()->create())
        ->post(route('licenses.checkin.save', $licenseSeat), [
            'notes' => 'my note',
            'redirect_option' => 'index',
        ])
        ->assertSessionHas('error', trans('admin/licenses/message.checkin.not_reassignable') . '.');

    expect($licenseSeat->fresh()->assigned_to)->not->toBeNull();
});

test('cannot checkin license that is not assigned', function () {
    $licenseSeat = LicenseSeat::factory()
        ->reassignable()
        ->create();

    expect($licenseSeat->assigned_to)->toBeNull();
    expect($licenseSeat->asset_id)->toBeNull();

    $this->actingAs(User::factory()->checkoutLicenses()->create())
        ->post(route('licenses.checkin.save', $licenseSeat), [
            'notes' => 'my note',
            'redirect_option' => 'index',
        ])
        ->assertSessionHas('error', trans('admin/licenses/message.checkin.error'));
});

test('can check in license assigned to asset', function () {
    Event::fake([CheckoutableCheckedIn::class]);

    $asset = Asset::factory()->create();

    $licenseSeat = LicenseSeat::factory()
        ->reassignable()
        ->assignedToAsset($asset)
        ->create();

    $actor = User::factory()->checkoutLicenses()->create();

    $this->actingAs($actor)
        ->post(route('licenses.checkin.save', $licenseSeat), [
            'notes' => 'my note',
            'redirect_option' => 'index',
        ])
        ->assertRedirect(route('licenses.index'));

    expect($licenseSeat->fresh()->asset_id)->toBeNull();
    expect($licenseSeat->fresh()->assigned_to)->toBeNull();
    expect($licenseSeat->fresh()->notes)->toEqual('my note');

    Event::assertDispatchedTimes(CheckoutableCheckedIn::class, 1);
    Event::assertDispatched(CheckoutableCheckedIn::class, function (CheckoutableCheckedIn $event) use ($actor, $asset, $licenseSeat) {
        return $event->checkoutable->is($licenseSeat)
            && $event->checkedOutTo->is($asset)
            && $event->checkedInBy->is($actor)
            && $event->note === 'my note';
    });
});

test('can check in license assigned to user', function () {
    Event::fake([CheckoutableCheckedIn::class]);

    $user = User::factory()->create();

    $licenseSeat = LicenseSeat::factory()
        ->reassignable()
        ->assignedToUser($user)
        ->create();

    $actor = User::factory()->checkoutLicenses()->create();

    $this->actingAs($actor)
        ->post(route('licenses.checkin.save', $licenseSeat), [
            'notes' => 'my note',
            'redirect_option' => 'index',
        ])
        ->assertRedirect(route('licenses.index'));

    expect($licenseSeat->fresh()->asset_id)->toBeNull();
    expect($licenseSeat->fresh()->assigned_to)->toBeNull();
    expect($licenseSeat->fresh()->notes)->toEqual('my note');

    Event::assertDispatchedTimes(CheckoutableCheckedIn::class, 1);
    Event::assertDispatched(CheckoutableCheckedIn::class, function (CheckoutableCheckedIn $event) use ($actor, $licenseSeat, $user) {
        return $event->checkoutable->is($licenseSeat)
            && $event->checkedOutTo->is($user)
            && $event->checkedInBy->is($actor)
            && $event->note === 'my note';
    });
});

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('licenses.checkin', LicenseSeat::factory()->assignedToUser()->create()->id))
        ->assertOk();
});
