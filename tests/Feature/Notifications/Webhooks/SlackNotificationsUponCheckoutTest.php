<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use App\Events\CheckoutableCheckedOut;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\LicenseSeat;
use App\Models\Location;
use App\Models\User;
use App\Notifications\CheckoutAccessoryNotification;
use App\Notifications\CheckoutAssetNotification;
use App\Notifications\CheckoutConsumableNotification;
use App\Notifications\CheckoutLicenseSeatNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

dataset('assetCheckoutTargets', function () {
    return [
        'Asset checked out to user' => [fn() => User::factory()->create()],
        'Asset checked out to asset' => [fn() => Asset::factory()->laptopMbp()->create()],
        'Asset checked out to location' => [fn() => Location::factory()->create()],
    ];
});

dataset('licenseCheckoutTargets', function () {
    return [
        'License checked out to user' => [fn() => User::factory()->create()],
        'License checked out to asset' => [fn() => Asset::factory()->laptopMbp()->create()],
    ];
});

test('accessory checkout sends slack notification when setting enabled', function () {
    $this->settings->enableSlackWebhook();

    fireCheckOutEvent(Accessory::factory()->create(), User::factory()->create());

    $this->assertSlackNotificationSent(CheckoutAccessoryNotification::class);
});

test('accessory checkout does not send slack notification when setting disabled', function () {
    $this->settings->disableSlackWebhook();

    fireCheckOutEvent(Accessory::factory()->create(), User::factory()->create());

    $this->assertNoSlackNotificationSent(CheckoutAccessoryNotification::class);
});

test('asset checkout sends slack notification when setting enabled', function ($checkoutTarget) {
    $this->settings->enableSlackWebhook();

    fireCheckOutEvent(Asset::factory()->create(), $checkoutTarget());

    $this->assertSlackNotificationSent(CheckoutAssetNotification::class);
})->with('assetCheckoutTargets');

test('asset checkout does not send slack notification when setting disabled', function ($checkoutTarget) {
    $this->settings->disableSlackWebhook();

    fireCheckOutEvent(Asset::factory()->create(), $checkoutTarget());

    $this->assertNoSlackNotificationSent(CheckoutAssetNotification::class);
})->with('assetCheckoutTargets');

test('component checkout does not send slack notification', function () {
    $this->settings->enableSlackWebhook();

    fireCheckOutEvent(Component::factory()->create(), Asset::factory()->laptopMbp()->create());

    Notification::assertNothingSent();
});

test('consumable checkout sends slack notification when setting enabled', function () {
    $this->settings->enableSlackWebhook();

    fireCheckOutEvent(Consumable::factory()->create(), User::factory()->create());

    $this->assertSlackNotificationSent(CheckoutConsumableNotification::class);
});

test('consumable checkout does not send slack notification when setting disabled', function () {
    $this->settings->disableSlackWebhook();

    fireCheckOutEvent(Consumable::factory()->create(), User::factory()->create());

    $this->assertNoSlackNotificationSent(CheckoutConsumableNotification::class);
});

test('license checkout sends slack notification when setting enabled', function ($checkoutTarget) {
    $this->settings->enableSlackWebhook();

    fireCheckOutEvent(LicenseSeat::factory()->create(), $checkoutTarget());

    $this->assertSlackNotificationSent(CheckoutLicenseSeatNotification::class);
})->with('licenseCheckoutTargets');

test('license checkout does not send slack notification when setting disabled', function ($checkoutTarget) {
    $this->settings->disableSlackWebhook();

    fireCheckOutEvent(LicenseSeat::factory()->create(), $checkoutTarget());

    $this->assertNoSlackNotificationSent(CheckoutLicenseSeatNotification::class);
})->with('licenseCheckoutTargets');

function fireCheckOutEvent(Model $checkoutable, Model $target)
{
    event(new CheckoutableCheckedOut(
        $checkoutable,
        $target,
        User::factory()->superuser()->create(),
        '',
    ));
}
