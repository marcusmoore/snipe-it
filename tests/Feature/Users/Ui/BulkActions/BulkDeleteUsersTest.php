<?php

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Consumable;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

test('requires correct permission', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('users/bulksave'), [
            'ids' => [
                User::factory()->create()->id,
            ],
            'status_id' => Statuslabel::factory()->create()->id,
        ])
        ->assertForbidden();
});

test('validation', function () {
    $user = User::factory()->create();
    Asset::factory()->assignedToUser($user)->create();

    $actor = $this->actingAs(User::factory()->editUsers()->create());

    // "ids" required
    $actor->post(route('users/bulksave'), [
        // 'ids' => [
        //     $user->id,
        // ],
        'status_id' => Statuslabel::factory()->create()->id,
    ])->assertSessionHas('error')->assertRedirect();

    // "status_id" needed when provided users have assets associated
    $actor->post(route('users/bulksave'), [
        'ids' => [
            $user->id,
        ],
        // 'status_id' => Statuslabel::factory()->create()->id,
    ])->assertSessionHas('error')->assertRedirect();
});

test('cannot perform bulk actions on self', function () {
    $actor = User::factory()->editUsers()->create();

    $this->actingAs($actor)
        ->post(route('users/bulksave'), [
            'ids' => [
                $actor->id,
            ],
            'delete_user' => '1',
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success', trans('general.bulk_checkin_delete_success'));

    $this->assertNotSoftDeleted($actor);
});

test('accessories can be bulk checked in', function () {
    [$accessoryA, $accessoryB] = Accessory::factory()->count(2)->create();
    [$userA, $userB, $userC] = User::factory()->count(3)->create();

    // Add checkouts for multiple accessories to multiple users to get different ids in the mix
    attachAccessoryToUsers($accessoryA, [$userA, $userB, $userC]);
    attachAccessoryToUsers($accessoryB, [$userA, $userB]);

    $this->actingAs(User::factory()->editUsers()->create())
        ->post(route('users/bulksave'), [
            'ids' => [
                $userA->id,
                $userC->id,
            ],
            'status_id' => Statuslabel::factory()->create()->id,
        ])
        ->assertRedirect(route('users.index'));

    expect($userA->fresh()->accessories->isEmpty())->toBeTrue();
    expect($userB->fresh()->accessories->isNotEmpty())->toBeTrue();
    expect($userC->fresh()->accessories->isEmpty())->toBeTrue();

    // These assertions check against a bug where the wrong value from
    // accessories_users was being populated in action_logs.item_id.
    assertActionLogCheckInEntryFor($userA, $accessoryA);
    assertActionLogCheckInEntryFor($userA, $accessoryB);
    assertActionLogCheckInEntryFor($userC, $accessoryA);
});

test('assets can be bulk checked in', function () {
    [$userA, $userB, $userC] = User::factory()->count(3)->create();

    $assetForUserA = assignAssetToUser($userA);
    $lonelyAsset = assignAssetToUser($userB);
    $assetForUserC = assignAssetToUser($userC);

    $this->actingAs(User::factory()->editUsers()->create())
        ->post(route('users/bulksave'), [
            'ids' => [
                $userA->id,
                $userC->id,
            ],
            'status_id' => Statuslabel::factory()->create()->id,
        ])
        ->assertRedirect(route('users.index'));

    expect($userA->fresh()->assets->isEmpty())->toBeTrue();
    expect($userB->fresh()->assets->isNotEmpty())->toBeTrue();
    expect($userC->fresh()->assets->isEmpty())->toBeTrue();

    assertActionLogCheckInEntryFor($userA, $assetForUserA);
    assertActionLogCheckInEntryFor($userC, $assetForUserC);
});

test('consumables can be bulk checked in', function () {
    [$consumableA, $consumableB] = Consumable::factory()->count(2)->create();
    [$userA, $userB, $userC] = User::factory()->count(3)->create();

    // Add checkouts for multiple consumables to multiple users to get different ids in the mix
    attachConsumableToUsers($consumableA, [$userA, $userB, $userC]);
    attachConsumableToUsers($consumableB, [$userA, $userB]);

    $this->actingAs(User::factory()->editUsers()->create())
        ->post(route('users/bulksave'), [
            'ids' => [
                $userA->id,
                $userC->id,
            ],
            'status_id' => Statuslabel::factory()->create()->id,
        ])
        ->assertRedirect(route('users.index'));

    expect($userA->fresh()->consumables->isEmpty())->toBeTrue();
    expect($userB->fresh()->consumables->isNotEmpty())->toBeTrue();
    expect($userC->fresh()->consumables->isEmpty())->toBeTrue();

    // Consumable checkin should not be logged.
    assertNoActionLogCheckInEntryFor($userA, $consumableA);
    assertNoActionLogCheckInEntryFor($userA, $consumableB);
    assertNoActionLogCheckInEntryFor($userC, $consumableA);
});

test('license seats can be bulk checked in', function () {
    [$userA, $userB, $userC] = User::factory()->count(3)->create();

    $licenseSeatForUserA = LicenseSeat::factory()->assignedToUser($userA)->create();
    $lonelyLicenseSeat = LicenseSeat::factory()->assignedToUser($userB)->create();
    $licenseSeatForUserC = LicenseSeat::factory()->assignedToUser($userC)->create();

    $this->actingAs(User::factory()->editUsers()->create())
        ->post(route('users/bulksave'), [
            'ids' => [
                $userA->id,
                $userC->id,
            ],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success', trans('general.bulk_checkin_success'));

    $this->assertDatabaseMissing('license_seats', [
        'license_id' => $licenseSeatForUserA->license->id,
        'assigned_to' => $userA->id,
    ]);

    $this->assertDatabaseMissing('license_seats', [
        'license_id' => $licenseSeatForUserC->license->id,
        'assigned_to' => $userC->id,
    ]);

    // Slightly different from the other assertions since we use
    // the license and not the license seat in this case.
    $this->assertDatabaseHas('action_logs', [
        'action_type' => 'checkin from',
        'target_id' => $userA->id,
        'target_type' => User::class,
        'note' => 'Bulk checkin items',
        'item_type' => License::class,
        'item_id' => $licenseSeatForUserA->license->id,
    ]);

    $this->assertDatabaseHas('action_logs', [
        'action_type' => 'checkin from',
        'target_id' => $userC->id,
        'target_type' => User::class,
        'note' => 'Bulk checkin items',
        'item_type' => License::class,
        'item_id' => $licenseSeatForUserC->license->id,
    ]);
});

test('users can be deleted in bulk', function () {
    [$userA, $userB, $userC] = User::factory()->count(3)->create();

    $this->actingAs(User::factory()->editUsers()->create())
        ->post(route('users/bulksave'), [
            'ids' => [
                $userA->id,
                $userC->id,
            ],
            'delete_user' => '1',
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success', trans('general.bulk_checkin_delete_success'));

    $this->assertSoftDeleted($userA);
    $this->assertNotSoftDeleted($userB);
    $this->assertSoftDeleted($userC);
});

function assignAssetToUser(User $user): Asset
{
    return Asset::factory()->assignedToUser($user)->create();
}

function attachAccessoryToUsers(Accessory $accessory, array $users): void
{
    foreach ($users as $user) {
        $accessoryCheckout = $accessory->checkouts()->make();
        $accessoryCheckout->assignedTo()->associate($user);
        $accessoryCheckout->save();
    }
}

function attachConsumableToUsers(Consumable $consumable, array $users): void
{
    foreach ($users as $user) {
        $consumable->users()->attach($consumable->id, [
            'consumable_id' => $consumable->id,
            'assigned_to' => $user->id,
        ]);
    }
}

function assertActionLogCheckInEntryFor(User $user, Model $model): void
{
    test()->assertDatabaseHas('action_logs', [
        'action_type' => 'checkin from',
        'target_id' => $user->id,
        'target_type' => User::class,
        'note' => 'Bulk checkin items',
        'item_type' => get_class($model),
        'item_id' => $model->id,
    ]);
}

function assertNoActionLogCheckInEntryFor(User $user, Model $model): void
{
    test()->assertDatabaseMissing('action_logs', [
        'action_type' => 'checkin from',
        'target_id' => $user->id,
        'target_type' => User::class,
        'note' => 'Bulk checkin items',
        'item_type' => get_class($model),
        'item_id' => $model->id,
    ]);
}
