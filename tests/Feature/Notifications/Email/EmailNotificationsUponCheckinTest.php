<?php

use PHPUnit\Framework\Attributes\Group;
use App\Events\CheckoutableCheckedIn;
use App\Models\Asset;
use App\Models\User;
use App\Notifications\CheckinAssetNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

test('check in email sent to user if setting enabled', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->assignedToUser($user)->create();

    $asset->model->category->update(['checkin_email' => true]);

    fireCheckInEvent($asset, $user);

    Notification::assertSentTo(
        $user,
        function (CheckinAssetNotification $notification, $channels) {
            return in_array('mail', $channels);
        },
    );
});

test('check in email not sent to user if setting disabled', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->assignedToUser($user)->create();

    $asset->model->category->update(['checkin_email' => false]);

    fireCheckInEvent($asset, $user);

    Notification::assertNotSentTo(
        $user,
        function (CheckinAssetNotification $notification, $channels) {
            return in_array('mail', $channels);
        }
    );
});

function fireCheckInEvent($asset, $user) : void
{
    event(new CheckoutableCheckedIn(
        $asset,
        $user,
        User::factory()->checkinAssets()->create(),
        ''
    ));
}
