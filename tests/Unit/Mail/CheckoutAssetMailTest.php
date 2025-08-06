<?php

use App\Mail\CheckoutAssetMail;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\User;

test('subject line and opening', function (
    Asset $asset,
    CheckoutAcceptance|null $acceptance,
    bool $firstTimeSending,
    string $expectedSubject,
    string $expectedOpening
) {
    (new CheckoutAssetMail(
        $asset,
        User::factory()->create(),
        User::factory()->create(),
        $acceptance,
        'A note goes here',
        $firstTimeSending,
    ))->assertHasSubject($expectedSubject)
        ->assertSeeInText($expectedOpening);
})->with([
    'Asset requiring acceptance' => [
        function () {
            $asset = Asset::factory()->requiresAcceptance()->create();
            return [
                'asset' => $asset,
                'acceptance' => CheckoutAcceptance::factory()->for($asset, 'checkoutable')->create(),
                'first_time_sending' => true,
                'expected_subject' => 'Asset checked out',
                'expected_opening' => 'A new item has been checked out under your name that requires acceptance, details are below.'
            ];
        }
    ],

    'Asset not requiring acceptance' => [
        function () {
            return [
                'asset' => Asset::factory()->doesNotRequireAcceptance()->create(),
                'acceptance' => null,
                'first_time_sending' => true,
                'expected_subject' => 'Asset checked out',
                'expected_opening' => 'A new item has been checked out under your name, details are below.'
            ];
        }
    ],

    'Reminder' => [
        function () {
            return [
                'asset' => Asset::factory()->requiresAcceptance()->create(),
                'acceptance' => CheckoutAcceptance::factory()->create(),
                'first_time_sending' => false,
                'expected_subject' => 'Reminder: You have Unaccepted Assets.',
                'expected_opening' => 'An item was recently checked out under your name that requires acceptance, details are below.'
            ];
        }
    ],
]);
