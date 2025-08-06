<?php

use App\Events\CheckoutableCheckedOut;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([CheckoutableCheckedOut::class]);
});

test('checkout request', function () {
    Notification::fake();
    $requestable = Asset::factory()->requestable()->create();
    $nonRequestable = Asset::factory()->nonrequestable()->create();

    $this->actingAsForApi(User::factory()->create())
        ->post(route('api.assets.requests.store', $requestable->id))
        ->assertStatusMessageIs('success');

    $this->actingAsForApi(User::factory()->create())
        ->post(route('api.assets.requests.store', $nonRequestable->id))
        ->assertStatusMessageIs('error');

    $this->assertHasTheseActionLogs($requestable, ['create', 'requested', 'update']);
    //FIXME - is this right?!
});

test('checking out asset requires correct permission', function () {
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.asset.checkout', Asset::factory()->create()), [
            'checkout_to_type' => 'user',
            'assigned_user' => User::factory()->create()->id,
        ])
        ->assertForbidden();
});

test('non existent asset cannot be checked out', function () {
    $this->actingAsForApi(User::factory()->checkoutAssets()->create())
        ->postJson(route('api.asset.checkout', 1000), [
            'checkout_to_type' => 'user',
            'assigned_user' => User::factory()->create()->id,
        ])
        ->assertStatusMessageIs('error');
});

test('asset not available for checkout cannot be checked out', function () {
    $assetAlreadyCheckedOut = Asset::factory()->assignedToUser()->create();

    $this->actingAsForApi(User::factory()->checkoutAssets()->create())
        ->postJson(route('api.asset.checkout', $assetAlreadyCheckedOut), [
            'checkout_to_type' => 'user',
            'assigned_user' => User::factory()->create()->id,
        ])
        ->assertStatusMessageIs('error');
});

test('asset cannot be checked out to itself', function () {
    $asset = Asset::factory()->create();

    $this->actingAsForApi(User::factory()->checkoutAssets()->create())
        ->postJson(route('api.asset.checkout', $asset), [
            'checkout_to_type' => 'asset',
            'assigned_asset' => $asset->id,
        ])
        ->assertStatusMessageIs('error');
});

test('validation when checking out asset', function () {
    $this->actingAsForApi(User::factory()->checkoutAssets()->create())
        ->postJson(route('api.asset.checkout', Asset::factory()->create()), [])
        ->assertStatusMessageIs('error');

    Event::assertNotDispatched(CheckoutableCheckedOut::class);
});

test('cannot checkout across companies when full company support enabled', function () {
    $this->markTestIncomplete('This is not implemented');
});

test('asset can be checked out', function (string $type, Model $target, Location|null $expectedLocation) {
    $newStatus = Statuslabel::factory()->readyToDeploy()->create();
    $asset = Asset::factory()->forLocation()->create();
    $admin = User::factory()->checkoutAssets()->create();

    $this->actingAsForApi($admin)
        ->postJson(route('api.asset.checkout', $asset), [
            'checkout_to_type' => $type,
            'assigned_' . $type => $target->id,
            'status_id' => $newStatus->id,
            'checkout_at' => '2024-04-01',
            'expected_checkin' => '2024-04-08',
            'name' => 'Changed Name',
            'note' => 'Here is a cool note!',
        ])
        ->assertOk();

    $asset->refresh();
    expect($asset->assignedTo()->is($target))->toBeTrue();
    expect($asset->name)->toEqual('Changed Name');
    expect($asset->assetstatus->is($newStatus))->toBeTrue();
    expect($asset->last_checkout)->toEqual('2024-04-01 00:00:00');
    expect((string) $asset->expected_checkin)->toEqual('2024-04-08 00:00:00');

    $expectedLocation
        ? expect($asset->location->is($expectedLocation))->toBeTrue()
        : expect($asset->location)->toBeNull();

    Event::assertDispatched(CheckoutableCheckedOut::class, 1);
    Event::assertDispatched(function (CheckoutableCheckedOut $event) use ($admin, $asset, $target) {
        expect($event->checkoutable->is($asset))->toBeTrue();
        expect($event->checkedOutTo->is($target))->toBeTrue();
        expect($event->checkedOutBy->is($admin))->toBeTrue();
        expect($event->note)->toEqual('Here is a cool note!');

        return true;
    });
})->with([
    'Checkout to User' => [
        function () {
            $userLocation = Location::factory()->create();
            $user = User::factory()->for($userLocation)->create();

            return [
                'checkout_type' => 'user',
                'target' => $user,
                'expected_location' => $userLocation,
            ];
        }
    ],
    'Checkout to User without location set' => [
        function () {
            $userLocation = Location::factory()->create();
            $user = User::factory()->for($userLocation)->create(['location_id' => null]);

            return [
                'checkout_type' => 'user',
                'target' => $user,
                'expected_location' => null,
            ];
        }
    ],
    'Checkout to Asset with location set' => [
        function () {
            $rtdLocation = Location::factory()->create();
            $location = Location::factory()->create();
            $asset = Asset::factory()->for($location)->for($rtdLocation, 'defaultLoc')->create();

            return [
                'checkout_type' => 'asset',
                'target' => $asset,
                'expected_location' => $location,
            ];
        }
    ],
    'Checkout to Asset without location set' => [
        function () {
            $rtdLocation = Location::factory()->create();
            $asset = Asset::factory()->for($rtdLocation, 'defaultLoc')->create(['location_id' => null]);

            return [
                'checkout_type' => 'asset',
                'target' => $asset,
                'expected_location' => null,
            ];
        }
    ],
    'Checkout to Location' => [
        function () {
            $location = Location::factory()->create();

            return [
                'checkout_type' => 'location',
                'target' => $location,
                'expected_location' => $location,
            ];
        }
    ],
]);

test('license seats are assigned to user upon checkout', function () {
    $this->markTestIncomplete('This is not implemented');
});

test('last checkout uses current date if not provided', function () {
    $asset = Asset::factory()->create(['last_checkout' => now()->subMonth()]);

    $this->actingAsForApi(User::factory()->checkoutAssets()->create())
        ->postJson(route('api.asset.checkout', $asset), [
            'checkout_to_type' => 'user',
            'assigned_user' => User::factory()->create()->id,
        ]);

    $asset->refresh();

    expect((int) Carbon::parse($asset->last_checkout)->diffInSeconds(now(), true) < 2)->toBeTrue();
});
