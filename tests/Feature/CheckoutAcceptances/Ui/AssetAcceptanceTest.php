<?php

use App\Events\CheckoutAccepted;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('asset checkout accept page renders', function () {
    $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

    $this->actingAs($checkoutAcceptance->assignedTo)
        ->get(route('account.accept.item', $checkoutAcceptance))
        ->assertViewIs('account.accept.create');
});

test('cannot accept asset already accepted', function () {
    Event::fake([CheckoutAccepted::class]);

    $checkoutAcceptance = CheckoutAcceptance::factory()->accepted()->create();

    expect($checkoutAcceptance->isPending())->toBeFalse();

    $this->actingAs($checkoutAcceptance->assignedTo)
        ->post(route('account.store-acceptance', $checkoutAcceptance), [
            'asset_acceptance' => 'accepted',
            'note' => 'my note',
        ])
        ->assertRedirectToRoute('account.accept')
        ->assertSessionHas('error');

    Event::assertNotDispatched(CheckoutAccepted::class);
});

test('cannot accept asset for another user', function () {
    Event::fake([CheckoutAccepted::class]);

    $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

    expect($checkoutAcceptance->isPending())->toBeTrue();

    $anotherUser = User::factory()->create();

    $this->actingAs($anotherUser)
        ->post(route('account.store-acceptance', $checkoutAcceptance), [
            'asset_acceptance' => 'accepted',
            'note' => 'my note',
        ])
        ->assertRedirectToRoute('account.accept')
        ->assertSessionHas('error');

    expect($checkoutAcceptance->fresh()->isPending())->toBeTrue();

    Event::assertNotDispatched(CheckoutAccepted::class);
});

test('user can accept asset', function () {
    Event::fake([CheckoutAccepted::class]);

    $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

    expect($checkoutAcceptance->isPending())->toBeTrue();

    $this->actingAs($checkoutAcceptance->assignedTo)
        ->post(route('account.store-acceptance', $checkoutAcceptance), [
            'asset_acceptance' => 'accepted',
            'note' => 'my note',
        ])
        ->assertRedirectToRoute('account.accept')
        ->assertSessionHas('success');

    $checkoutAcceptance->refresh();

    expect($checkoutAcceptance->isPending())->toBeFalse();
    expect($checkoutAcceptance->accepted_at)->not->toBeNull();
    expect($checkoutAcceptance->declined_at)->toBeNull();

    Event::assertDispatched(CheckoutAccepted::class);
});

test('user can decline asset', function () {
    Event::fake([CheckoutAccepted::class]);

    $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

    expect($checkoutAcceptance->isPending())->toBeTrue();

    $this->actingAs($checkoutAcceptance->assignedTo)
        ->post(route('account.store-acceptance', $checkoutAcceptance), [
            'asset_acceptance' => 'declined',
            'note' => 'my note',
        ])
        ->assertRedirectToRoute('account.accept')
        ->assertSessionHas('success');

    $checkoutAcceptance->refresh();

    expect($checkoutAcceptance->isPending())->toBeFalse();
    expect($checkoutAcceptance->accepted_at)->toBeNull();
    expect($checkoutAcceptance->declined_at)->not->toBeNull();

    Event::assertNotDispatched(CheckoutAccepted::class);
});

test('action logged when accepting asset', function () {
    $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

    $this->actingAs($checkoutAcceptance->assignedTo)
        ->post(route('account.store-acceptance', $checkoutAcceptance), [
            'asset_acceptance' => 'accepted',
            'note' => 'my note',
        ]);

    expect(Actionlog::query()
        ->where([
            'action_type' => 'accepted',
            'target_id' => $checkoutAcceptance->assignedTo->id,
            'target_type' => User::class,
            'note' => 'my note',
            'item_type' => Asset::class,
            'item_id' => $checkoutAcceptance->checkoutable->id,
        ])
        ->whereNotNull('action_date')
        ->exists())->toBeTrue();
});

test('action logged when declining asset', function () {
    $checkoutAcceptance = CheckoutAcceptance::factory()->pending()->create();

    $this->actingAs($checkoutAcceptance->assignedTo)
        ->post(route('account.store-acceptance', $checkoutAcceptance), [
            'asset_acceptance' => 'declined',
            'note' => 'my note',
        ]);

    expect(Actionlog::query()
        ->where([
            'action_type' => 'declined',
            'target_id' => $checkoutAcceptance->assignedTo->id,
            'target_type' => User::class,
            'note' => 'my note',
            'item_type' => Asset::class,
            'item_id' => $checkoutAcceptance->checkoutable->id,
        ])
        ->whereNotNull('action_date')
        ->exists())->toBeTrue();
});
