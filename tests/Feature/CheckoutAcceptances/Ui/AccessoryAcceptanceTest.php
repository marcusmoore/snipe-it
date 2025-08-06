<?php

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\User;
use App\Notifications\AcceptanceAssetAcceptedNotification;
use App\Notifications\AcceptanceAssetDeclinedNotification;
use Illuminate\Support\Facades\Notification;

test('users name is included in accessory accepted notification', function () {
    Notification::fake();

    $this->settings->enableAlertEmail();

    $acceptance = CheckoutAcceptance::factory()
        ->pending()
        ->for(Accessory::factory()->appleMouse(), 'checkoutable')
        ->create();

    $this->actingAs($acceptance->assignedTo)
        ->post(route('account.store-acceptance', $acceptance), ['asset_acceptance' => 'accepted'])
        ->assertSessionHasNoErrors();

    expect($acceptance->fresh()->accepted_at)->not->toBeNull();

    Notification::assertSentTo(
        $acceptance,
        function (AcceptanceAssetAcceptedNotification $notification) use ($acceptance) {
            $this->assertStringContainsString(
                $acceptance->assignedTo->present()->fullName,
                $notification->toMail()->render()
            );

            return true;
        }
    );
});

test('users name is included in accessory declined notification', function () {
    Notification::fake();

    $this->settings->enableAlertEmail();

    $acceptance = CheckoutAcceptance::factory()
        ->pending()
        ->for(Accessory::factory()->appleMouse(), 'checkoutable')
        ->create();

    $this->actingAs($acceptance->assignedTo)
        ->post(route('account.store-acceptance', $acceptance), ['asset_acceptance' => 'declined'])
        ->assertSessionHasNoErrors();

    expect($acceptance->fresh()->declined_at)->not->toBeNull();

    Notification::assertSentTo(
        $acceptance,
        function (AcceptanceAssetDeclinedNotification $notification) use ($acceptance) {
            $this->assertStringContainsString(
                $acceptance->assignedTo->present()->fullName,
                $notification->toMail($acceptance)->render()
            );

            return true;
        }
    );
});

test('user is not able to accept an asset assigned to adifferent user', function () {
    Notification::fake();

    $otherUser = User::factory()->create();

    $acceptance = CheckoutAcceptance::factory()
        ->pending()
        ->for(Asset::factory()->laptopMbp(), 'checkoutable')
        ->create();

    $this->actingAs($otherUser)
        ->post(route('account.store-acceptance', $acceptance), ['asset_acceptance' => 'accepted'])
        ->assertSessionHas(['error' => trans('admin/users/message.error.incorrect_user_accepted')]);

    expect($acceptance->fresh()->accepted_at)->toBeNull();
});
