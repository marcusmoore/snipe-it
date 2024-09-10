<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use App\Events\CheckoutableCheckedIn;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\LicenseSeat;
use App\Models\Location;
use App\Models\User;
use App\Notifications\CheckinAccessoryNotification;
use App\Notifications\CheckinAssetNotification;
use App\Notifications\CheckinLicenseSeatNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

dataset('assetCheckInTargets', function () {
    return [
        'Asset checked out to user' => [fn() => User::factory()->create()],
        'Asset checked out to asset' => [fn() => Asset::factory()->laptopMbp()->create()],
        'Asset checked out to location' => [fn() => Location::factory()->create()],
    ];
});

dataset('licenseCheckInTargets', function () {
    return [
        'License checked out to user' => [fn() => User::factory()->create()],
        'License checked out to asset' => [fn() => Asset::factory()->laptopMbp()->create()],
    ];
});

test('accessory checkin sends slack notification when setting enabled', function () {
    $this->settings->enableSlackWebhook();

    fireCheckInEvent(Accessory::factory()->create(), User::factory()->create());

    $this->assertSlackNotificationSent(CheckinAccessoryNotification::class);
});

test('accessory checkin does not send slack notification when setting disabled', function () {
    $this->settings->disableSlackWebhook();

    fireCheckInEvent(Accessory::factory()->create(), User::factory()->create());

    $this->assertNoSlackNotificationSent(CheckinAccessoryNotification::class);
});

test('asset checkin sends slack notification when setting enabled', function ($checkoutTarget) {
    $this->settings->enableSlackWebhook();

    fireCheckInEvent(Asset::factory()->create(), $checkoutTarget());

    $this->assertSlackNotificationSent(CheckinAssetNotification::class);
})->with('assetCheckInTargets');

test('asset checkin does not send slack notification when setting disabled', function ($checkoutTarget) {
    $this->settings->disableSlackWebhook();

    fireCheckInEvent(Asset::factory()->create(), $checkoutTarget());

    $this->assertNoSlackNotificationSent(CheckinAssetNotification::class);
})->with('assetCheckInTargets');

test('component checkin does not send slack notification', function () {
    $this->settings->enableSlackWebhook();

    fireCheckInEvent(Component::factory()->create(), Asset::factory()->laptopMbp()->create());

    Notification::assertNothingSent();
});

test('license checkin sends slack notification when setting enabled', function ($checkoutTarget) {
    $this->settings->enableSlackWebhook();

    fireCheckInEvent(LicenseSeat::factory()->create(), $checkoutTarget());

    $this->assertSlackNotificationSent(CheckinLicenseSeatNotification::class);
})->with('licenseCheckInTargets');

test('license checkin does not send slack notification when setting disabled', function ($checkoutTarget) {
    $this->settings->disableSlackWebhook();

    fireCheckInEvent(LicenseSeat::factory()->create(), $checkoutTarget());

    $this->assertNoSlackNotificationSent(CheckinLicenseSeatNotification::class);
})->with('licenseCheckInTargets');

function fireCheckInEvent(Model $checkoutable, Model $target)
{
    event(new CheckoutableCheckedIn(
        $checkoutable,
        $target,
        User::factory()->superuser()->create(),
        ''
    ));
}
