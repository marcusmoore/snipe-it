<?php

use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\User;
use App\Notifications\CheckoutAccessoryNotification;
use Illuminate\Support\Facades\Notification;

test('checking out accessory requires correct permission', function () {
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.accessories.checkout', Accessory::factory()->create()))
        ->assertForbidden();
});

test('validation when checking out accessory', function () {
    $this->actingAsForApi(User::factory()->checkoutAccessories()->create())
        ->postJson(route('api.accessories.checkout', Accessory::factory()->create()), [
            // missing assigned_user, assigned_location, assigned_asset
        ])
        ->assertStatusMessageIs('error');
});

test('accessory must be available when checking out', function () {
    $this->actingAsForApi(User::factory()->checkoutAccessories()->create())
        ->postJson(route('api.accessories.checkout', Accessory::factory()->withoutItemsRemaining()->create()), [
            'assigned_user' => User::factory()->create()->id,
            'checkout_to_type' => 'user'
        ])
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->assertJson(
            [
            'status' => 'error',
            'messages' =>
                [
                    'checkout_qty' =>
                        [
                            trans_choice('admin/accessories/message.checkout.checkout_qty.lte', 0,
                                [
                                    'number_currently_remaining' => 0,
                                    'checkout_qty' => 1,
                                    'number_remaining_after_checkout' => 0
                                ])
                        ],

                ],
                'payload' => null,
            ])
        ->assertStatus(200)
        ->json();
});

test('accessory can be checked out without qty', function () {
    $accessory = Accessory::factory()->create();
    $user = User::factory()->create();
    $admin = User::factory()->checkoutAccessories()->create();

    $this->actingAsForApi($admin)
        ->postJson(route('api.accessories.checkout', $accessory), [
            'assigned_user' => $user->id,
            'checkout_to_type' => 'user'
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->assertStatus(200)
        ->assertJson(['messages' =>  trans('admin/accessories/message.checkout.success')])
        ->json();

    expect($accessory->checkouts()->where('assigned_type', User::class)->where('assigned_to', $user->id)->count() > 0)->toBeTrue();

    expect(Actionlog::where([
        'action_type' => 'checkout',
        'target_id' => $user->id,
        'target_type' => User::class,
        'item_id' => $accessory->id,
        'item_type' => Accessory::class,
        'user_id' => $admin->id,
    ])->count())->toEqual(1, 'Log entry either does not exist or there are more than expected');
});

test('accessory can be checked out with qty', function () {
    $accessory = Accessory::factory()->create(['qty' => 20]);
    $user = User::factory()->create();
    $admin = User::factory()->checkoutAccessories()->create();

    $this->actingAsForApi($admin)
        ->postJson(route('api.accessories.checkout', $accessory), [
            'assigned_user' => $user->id,
            'checkout_to_type' => 'user',
            'checkout_qty' => 2,
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->assertStatus(200)
        ->assertJson(['messages' =>  trans('admin/accessories/message.checkout.success')])
        ->json();

    expect($accessory->checkouts()->where('assigned_type', User::class)->where('assigned_to', $user->id)->count() > 0)->toBeTrue();

    expect(Actionlog::where([
        'action_type' => 'checkout',
        'target_id' => $user->id,
        'target_type' => User::class,
        'item_id' => $accessory->id,
        'item_type' => Accessory::class,
        'user_id' => $admin->id,
    ])->count())->toEqual(1, 'Log entry either does not exist or there are more than expected');
});

test('accessory cannot be checked out to invalid user', function () {
    $accessory = Accessory::factory()->create();
    $user = User::factory()->create();

    $this->actingAsForApi(User::factory()->checkoutAccessories()->create())
        ->postJson(route('api.accessories.checkout', $accessory), [
            'assigned_user' => 'invalid-user-id',
            'checkout_to_type' => 'user',
            'note' => 'oh hi there',
        ])
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->assertStatus(200)
        ->json();

    expect($accessory->checkouts()->where('assigned_type', User::class)->where('assigned_to', $user->id)->count() > 0)->toBeFalse();
});

test('user sent notification upon checkout', function () {
    Notification::fake();

    $accessory = Accessory::factory()->requiringAcceptance()->create();
    $user = User::factory()->create();

    $this->actingAsForApi(User::factory()->checkoutAccessories()->create())
        ->postJson(route('api.accessories.checkout', $accessory), [
            'assigned_user' => $user->id,
            'checkout_to_type' => 'user',
        ]);

    Notification::assertSentTo($user, CheckoutAccessoryNotification::class);
});

test('action log created upon checkout', function () {
    $accessory = Accessory::factory()->create();
    $actor = User::factory()->checkoutAccessories()->create();
    $user = User::factory()->create();

    $this->actingAsForApi($actor)
        ->postJson(route('api.accessories.checkout', $accessory), [
            'assigned_user' => $user->id,
            'checkout_to_type' => 'user',
            'note' => 'oh hi there',
        ]);

    expect(Actionlog::where([
        'action_type' => 'checkout',
        'target_id' => $user->id,
        'target_type' => User::class,
        'item_id' => $accessory->id,
        'item_type' => Accessory::class,
        'user_id' => $actor->id,
        'note' => 'oh hi there',
    ])->count())->toEqual(1, 'Log entry either does not exist or there are more than expected');
});
