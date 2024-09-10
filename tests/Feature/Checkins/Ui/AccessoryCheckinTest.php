<?php

use App\Events\CheckoutableCheckedIn;
use App\Models\Accessory;
use App\Models\User;
use App\Notifications\CheckinAccessoryNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

test('checking in accessory requires correct permission', function () {
    $accessory = Accessory::factory()->checkedOutToUser()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('accessories.checkin.store', $accessory->checkouts->first()->id))
        ->assertForbidden();
});

test('accessory can be checked in', function () {
    Event::fake([CheckoutableCheckedIn::class]);

    $user = User::factory()->create();
    $accessory = Accessory::factory()->checkedOutToUser($user)->create();

    expect($accessory->checkouts()->where('assigned_type', User::class)->where('assigned_to', $user->id)->count() > 0)->toBeTrue();

    $this->actingAs(User::factory()->checkinAccessories()->create())
        ->post(route('accessories.checkin.store', $accessory->checkouts->first()->id));

    expect($accessory->fresh()->checkouts()->where('assigned_type', User::class)->where('assigned_to', $user->id)->count() > 0)->toBeFalse();

    Event::assertDispatched(CheckoutableCheckedIn::class, 1);
});

test('email sent to user if setting enabled', function () {
    Notification::fake();

    $user = User::factory()->create();
    $accessory = Accessory::factory()->checkedOutToUser($user)->create();

    $accessory->category->update(['checkin_email' => true]);

    event(new CheckoutableCheckedIn(
        $accessory,
        $user,
        User::factory()->checkinAccessories()->create(),
        '',
    ));

    Notification::assertSentTo(
        [$user],
        function (CheckinAccessoryNotification $notification, $channels) {
            return in_array('mail', $channels);
        },
    );
});

test('email not sent to user if setting disabled', function () {
    Notification::fake();

    $user = User::factory()->create();
    $accessory = Accessory::factory()->checkedOutToUser($user)->create();

    $accessory->category->update(['checkin_email' => false]);

    event(new CheckoutableCheckedIn(
        $accessory,
        $user,
        User::factory()->checkinAccessories()->create(),
        '',
    ));

    Notification::assertNotSentTo(
        [$user],
        function (CheckinAccessoryNotification $notification, $channels) {
            return in_array('mail', $channels);
        },
    );
});
