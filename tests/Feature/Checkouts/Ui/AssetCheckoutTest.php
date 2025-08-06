<?php

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Events\CheckoutableCheckedOut;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Company;
use App\Models\LicenseSeat;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([CheckoutableCheckedOut::class]);
});

test('checking out asset requires correct permission', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('hardware.checkout.store', Asset::factory()->create()), [
            'checkout_to_type' => 'user',
            'assigned_user' => User::factory()->create()->id,
        ])
        ->assertForbidden();
});

test('non existent asset cannot be checked out', function () {
    $this->actingAs(User::factory()->checkoutAssets()->create())
        ->post(route('hardware.checkout.store', 1000), [
            'checkout_to_type' => 'user',
            'assigned_user' => User::factory()->create()->id,
            'name' => 'Changed Name',
        ])
        ->assertSessionHas('error')
        ->assertRedirect(route('hardware.index'));

    Event::assertNotDispatched(CheckoutableCheckedOut::class);
});

test('asset not available for checkout cannot be checked out', function () {
    $assetAlreadyCheckedOut = Asset::factory()->assignedToUser()->create();

    $this->actingAs(User::factory()->checkoutAssets()->create())
        ->post(route('hardware.checkout.store', $assetAlreadyCheckedOut), [
            'checkout_to_type' => 'user',
            'assigned_user' => User::factory()->create()->id,
        ])
        ->assertSessionHas('error')
        ->assertRedirect(route('hardware.index'));

    Event::assertNotDispatched(CheckoutableCheckedOut::class);
});

test('asset cannot be checked out to itself', function () {
    $asset = Asset::factory()->create();

    $this->actingAs(User::factory()->checkoutAssets()->create())
        ->post(route('hardware.checkout.store', $asset), [
            'checkout_to_type' => 'asset',
            'assigned_asset' => $asset->id,
        ])
        ->assertSessionHas('error');

    Event::assertNotDispatched(CheckoutableCheckedOut::class);
});

test('validation when checking out asset', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('hardware.checkout.store', Asset::factory()->create()), [
            'status_id' => 'does-not-exist',
            'checkout_at' => 'invalid-date',
            'expected_checkin' => 'invalid-date',
        ])
        ->assertSessionHasErrors([
            'assigned_user',
            'assigned_asset',
            'assigned_location',
            'status_id',
            'checkout_to_type',
            'checkout_at',
            'expected_checkin',
        ]);

    Event::assertNotDispatched(CheckoutableCheckedOut::class);
});

test('cannot checkout across companies when full company support enabled', function () {
    $this->settings->enableMultipleFullCompanySupport();

    $assetCompany = Company::factory()->create();
    $userCompany = Company::factory()->create();

    $user = User::factory()->for($userCompany)->create();
    $asset = Asset::factory()->for($assetCompany)->create();

    $this->actingAs(User::factory()->superuser()->create())
        ->post(route('hardware.checkout.store', $asset), [
            'checkout_to_type' => 'user',
            'assigned_user' => $user->id,
        ])
        ->assertRedirect(route('hardware.checkout.store', $asset));

    Event::assertNotDispatched(CheckoutableCheckedOut::class);
});

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('hardware.checkout.create', Asset::factory()->create()))
        ->assertOk();
});

test('asset can be checked out', function (string $type, Model $target, Location|null $expectedLocation) {
    $newStatus = Statuslabel::factory()->readyToDeploy()->create();
    $asset = Asset::factory()->create();
    $admin = User::factory()->checkoutAssets()->create();

    $defaultFieldsAlwaysIncludedInUIFormSubmission = [
        'assigned_user' => null,
        'assigned_asset' => null,
        'assigned_location' => null,
    ];

    $this->actingAs($admin)
        ->post(route('hardware.checkout.store', $asset), array_merge($defaultFieldsAlwaysIncludedInUIFormSubmission, [
            'checkout_to_type' => $type,
            // overwrite the value from the default fields set above
            'assigned_' . $type => (string) $target->id,
            'name' => 'Changed Name',
            'status_id' => (string) $newStatus->id,
            'checkout_at' => '2024-03-18',
            'expected_checkin' => '2024-03-28',
            'note' => 'An awesome note',
        ]));

    $asset->refresh();
    expect($asset->assignedTo()->is($target))->toBeTrue();
    expect($asset->location->is($expectedLocation))->toBeTrue();
    expect($asset->name)->toEqual('Changed Name');
    expect($asset->assetstatus->is($newStatus))->toBeTrue();
    expect($asset->last_checkout)->toEqual('2024-03-18 00:00:00');
    expect((string) $asset->expected_checkin)->toEqual('2024-03-28 00:00:00');

    Event::assertDispatched(CheckoutableCheckedOut::class, 1);
    Event::assertDispatched(function (CheckoutableCheckedOut $event) use ($admin, $asset, $target) {
        expect($event->checkoutable->is($asset))->toBeTrue();
        expect($event->checkedOutTo->is($target))->toBeTrue();
        expect($event->checkedOutBy->is($admin))->toBeTrue();
        expect($event->note)->toEqual('An awesome note');

        return true;
    });
    $this->assertHasTheseActionLogs($asset, ['create'/*, 'checkout'*/]);
    //TODO - only getting one?
})
    ->with([
        'User' => [
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
        'Asset without location set' => [
            function () {
                $rtdLocation = Location::factory()->create();
                $asset = Asset::factory()->for($rtdLocation, 'defaultLoc')->create(['location_id' => null]);

                return [
                    'checkout_type' => 'asset',
                    'target' => $asset,
                    'expected_location' => $rtdLocation,
                ];
            }
        ],
        'Asset with location set' => [
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
        'Location' => [
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
    $asset = Asset::factory()->create();
    $seat = LicenseSeat::factory()->assignedToAsset($asset)->create();
    $user = User::factory()->create();

    expect($user->licenses->contains($seat->license))->toBeFalse();

    $this->actingAs(User::factory()->checkoutAssets()->create())
        ->post(route('hardware.checkout.store', $asset), [
            'checkout_to_type' => 'user',
            'assigned_user' => $user->id,
        ]);

    expect($user->fresh()->licenses->contains($seat->license))->toBeTrue();
});

test('last checkout uses current date if not provided', function () {
    $asset = Asset::factory()->create(['last_checkout' => now()->subMonth()]);

    $this->actingAs(User::factory()->checkoutAssets()->create())
        ->post(route('hardware.checkout.store', $asset), [
            'checkout_to_type' => 'user',
            'assigned_user' => User::factory()->create()->id,
        ]);

    $asset->refresh();

    expect((int) Carbon::parse($asset->last_checkout)->diffInSeconds(now(), true) < 2)->toBeTrue();
});

test('asset checkout page is redirected if model is invalid', function () {
    $asset = Asset::factory()->create();
    $asset->model_id = 0;
    $asset->forceSave();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('hardware.checkout.create', $asset))
        ->assertStatus(302)
        ->assertSessionHas('error')
        ->assertRedirect(route('hardware.show', $asset));
});

test('asset checkout page post is redirected if redirect selection is index', function () {
    $asset = Asset::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('hardware.checkout.create', $asset))
        ->post(route('hardware.checkout.store', $asset), [
            'checkout_to_type' => 'user',
            'assigned_user' => User::factory()->create()->id,
            'redirect_option' => 'index',
        ])
        ->assertStatus(302)
        ->assertRedirect(route('hardware.index'));
});

test('asset checkout page post is redirected if redirect selection is item', function () {
    $asset = Asset::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('hardware.checkout.create', $asset))
        ->post(route('hardware.checkout.store', $asset), [
            'checkout_to_type' => 'user',
            'assigned_user' => User::factory()->create()->id,
            'redirect_option' => 'item',
        ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('hardware.show', $asset));
});

test('asset checkout page post is redirected if redirect selection is user target', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('hardware.checkout.create', $asset))
        ->post(route('hardware.checkout.store', $asset), [
            'checkout_to_type' => 'user',
            'assigned_user' => $user->id,
            'redirect_option' => 'target',
            'assigned_qty' => 1,
        ])
        ->assertStatus(302)
        ->assertRedirect(route('users.show', ['user' => $user]));
});

test('asset checkout page post is redirected if redirect selection is asset target', function () {
    $target = Asset::factory()->create();
    $asset = Asset::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('hardware.checkout.create', $asset))
        ->post(route('hardware.checkout.store', $asset), [
            'checkout_to_type' => 'asset',
            'assigned_asset' => $target->id,
            'redirect_option' => 'target',
            'assigned_qty' => 1,
        ])
        ->assertStatus(302)
        ->assertRedirect(route('hardware.show', $target));
});

test('asset checkout page post is redirected if redirect selection is location target', function () {
    $target = Location::factory()->create();
    $asset = Asset::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('hardware.checkout.create', $asset))
        ->post(route('hardware.checkout.store', $asset), [
            'checkout_to_type' => 'location',
            'assigned_location' => $target->id,
            'redirect_option' => 'target',
            'assigned_qty' => 1,
        ])
        ->assertStatus(302)
        ->assertRedirect(route('locations.show', ['location' => $target]));
});
