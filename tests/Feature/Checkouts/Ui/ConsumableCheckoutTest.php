<?php

use App\Mail\CheckoutConsumableMail;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\User;
use App\Notifications\CheckoutConsumableNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

test('checking out consumable requires correct permission', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('consumables.checkout.store', Consumable::factory()->create()))
        ->assertForbidden();
});

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('consumables.checkout.show', Consumable::factory()->create()->id))
        ->assertOk();
});

test('validation when checking out consumable', function () {
    $this->actingAs(User::factory()->checkoutConsumables()->create())
        ->post(route('consumables.checkout.store', Consumable::factory()->create()), [
            // missing assigned_to
        ])
        ->assertSessionHas('error');
});

test('consumable must be available when checking out', function () {
    $this->actingAs(User::factory()->checkoutConsumables()->create())
        ->post(route('consumables.checkout.store', Consumable::factory()->withoutItemsRemaining()->create()), [
            'assigned_to' => User::factory()->create()->id,
        ])
        ->assertSessionHas('error');
});

test('consumable can be checked out', function () {
    $consumable = Consumable::factory()->create();
    $user = User::factory()->create();

    $this->actingAs(User::factory()->checkoutConsumables()->create())
        ->post(route('consumables.checkout.store', $consumable), [
            'assigned_to' => $user->id,
        ]);

    expect($user->consumables->contains($consumable))->toBeTrue();
    $this->assertHasTheseActionLogs($consumable, ['create', 'checkout']);
});

test('user sent notification upon checkout', function () {
    Mail::fake();

    $consumable = Consumable::factory()->create();
    $user = User::factory()->create();

    $this->actingAs(User::factory()->checkoutConsumables()->create())
        ->post(route('consumables.checkout.store', $consumable), [
            'assigned_to' => $user->id,
        ]);

    Mail::assertSent(CheckoutConsumableMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

test('action log created upon checkout', function () {
    $consumable = Consumable::factory()->create();
    $actor = User::factory()->checkoutConsumables()->create();
    $user = User::factory()->create();

    $this->actingAs($actor)
        ->post(route('consumables.checkout.store', $consumable), [
            'assigned_to' => $user->id,
            'note' => 'oh hi there',
        ]);

    expect(Actionlog::where([
        'action_type' => 'checkout',
        'target_id' => $user->id,
        'target_type' => User::class,
        'item_id' => $consumable->id,
        'item_type' => Consumable::class,
        'created_by' => $actor->id,
        'note' => 'oh hi there',
    ])->count())->toEqual(1, 'Log entry either does not exist or there are more than expected');
});

test('consumable checkout page post is redirected if redirect selection is index', function () {
    $consumable = Consumable::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('consumables.index'))
        ->post(route('consumables.checkout.store', $consumable), [
            'assigned_to' =>  User::factory()->create()->id,
            'redirect_option' => 'index',
            'assigned_qty' => 1,
        ])
        ->assertStatus(302)
        ->assertRedirect(route('consumables.index'));
});

test('consumable checkout page post is redirected if redirect selection is item', function () {
    $consumable = Consumable::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('consumables.index'))
        ->post(route('consumables.checkout.store' , $consumable), [
            'assigned_to' =>  User::factory()->create()->id,
            'redirect_option' => 'item',
            'assigned_qty' => 1,
        ])
        ->assertStatus(302)
        ->assertRedirect(route('consumables.show', $consumable));
});

test('consumable checkout page post is redirected if redirect selection is target', function () {
    $user = User::factory()->create();
    $consumable = Consumable::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('components.index'))
        ->post(route('consumables.checkout.store' , $consumable), [
            'assigned_to' =>  $user->id,
            'redirect_option' => 'target',
            'assigned_qty' => 1,
        ])
        ->assertStatus(302)
        ->assertRedirect(route('users.show', $user));
});
