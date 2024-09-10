<?php

use App\Models\Actionlog;
use App\Models\Consumable;
use App\Models\User;
use App\Notifications\CheckoutConsumableNotification;
use Illuminate\Support\Facades\Notification;

test('checking out consumable requires correct permission', function () {
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.consumables.checkout', Consumable::factory()->create()))
        ->assertForbidden();
});

test('validation when checking out consumable', function () {
    $this->actingAsForApi(User::factory()->checkoutConsumables()->create())
        ->postJson(route('api.consumables.checkout', Consumable::factory()->create()), [
            // missing assigned_to
        ])
        ->assertStatusMessageIs('error');
});

test('consumable must be available when checking out', function () {
    $this->actingAsForApi(User::factory()->checkoutConsumables()->create())
        ->postJson(route('api.consumables.checkout', Consumable::factory()->withoutItemsRemaining()->create()), [
            'assigned_to' => User::factory()->create()->id,
        ])
        ->assertStatusMessageIs('error');
});

test('consumable can be checked out', function () {
    $consumable = Consumable::factory()->create();
    $user = User::factory()->create();

    $this->actingAsForApi(User::factory()->checkoutConsumables()->create())
        ->postJson(route('api.consumables.checkout', $consumable), [
            'assigned_to' => $user->id,
        ]);

    expect($user->consumables->contains($consumable))->toBeTrue();
});

test('user sent notification upon checkout', function () {
    Notification::fake();

    $consumable = Consumable::factory()->requiringAcceptance()->create();

    $user = User::factory()->create();

    $this->actingAsForApi(User::factory()->checkoutConsumables()->create())
        ->postJson(route('api.consumables.checkout', $consumable), [
            'assigned_to' => $user->id,
        ]);

    Notification::assertSentTo($user, CheckoutConsumableNotification::class);
});

test('action log created upon checkout', function () {
    $consumable = Consumable::factory()->create();
    $actor = User::factory()->checkoutConsumables()->create();
    $user = User::factory()->create();

    $this->actingAsForApi($actor)
        ->postJson(route('api.consumables.checkout', $consumable), [
            'assigned_to' => $user->id,
            'note' => 'oh hi there',
        ]);

    expect(Actionlog::where([
        'action_type' => 'checkout',
        'target_id' => $user->id,
        'target_type' => User::class,
        'item_id' => $consumable->id,
        'item_type' => Consumable::class,
        'user_id' => $actor->id,
        'note' => 'oh hi there',
    ])->count())->toEqual(1, 'Log entry either does not exist or there are more than expected');
});
