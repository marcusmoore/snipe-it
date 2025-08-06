<?php

use App\Mail\CheckoutAccessoryMail;
use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('checking out accessory requires correct permission', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('accessories.checkout.store', Accessory::factory()->create()))
        ->assertForbidden();
});

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('accessories.checkout.show', Accessory::factory()->create()))
        ->assertOk();
});

test('validation when checking out accessory', function () {
    $accessory = Accessory::factory()->create();
    $response = $this->actingAs(User::factory()->superuser()->create())
        ->from(route('accessories.checkout.show', $accessory))
        ->post(route('accessories.checkout.store', $accessory), [
            // missing assigned_to
        ])
        ->assertStatus(302)
        ->assertSessionHas('errors')
        ->assertRedirect(route('accessories.checkout.store', $accessory));

    $this->followRedirects($response)->assertSee(trans('general.error'));
});

test('accessory must have available items for checkout when checking out', function () {
    $accessory = Accessory::factory()->withoutItemsRemaining()->create();
    $response = $this->actingAs(User::factory()->viewAccessories()->checkoutAccessories()->create())
        ->from(route('accessories.checkout.show', $accessory))
        ->post(route('accessories.checkout.store', $accessory), [
            'assigned_user' => User::factory()->create()->id,
            'checkout_to_type' => 'user',
        ])
        ->assertStatus(302)
        ->assertSessionHas('errors')
        ->assertRedirect(route('accessories.checkout.store', $accessory));
    $response->assertInvalid(['checkout_qty']);
    $this->followRedirects($response)->assertSee(trans('general.error'));
});

test('accessory can be checked out without quantity', function () {
    $accessory = Accessory::factory()->create();
    $user = User::factory()->create();

    $this->actingAs(User::factory()->checkoutAccessories()->create())
        ->post(route('accessories.checkout.store', $accessory), [
            'assigned_user' => $user->id,
            'checkout_to_type' => 'user',
            'note' => 'oh hi there',
        ]);

    expect($accessory->checkouts()->where('assigned_type', User::class)->where('assigned_to', $user->id)->count() > 0)->toBeTrue();

    $this->assertDatabaseHas('action_logs', [
        'action_type' => 'checkout',
        'target_id' => $user->id,
        'target_type' => User::class,
        'item_id' => $accessory->id,
        'item_type' => Accessory::class,
        'note' => 'oh hi there',
    ]);
    $this->assertHasTheseActionLogs($accessory, ['create', 'checkout']);
});

test('accessory can be checked out with quantity', function () {
    $accessory = Accessory::factory()->create(['qty'=>5]);
    $user = User::factory()->create();

    $this->actingAs(User::factory()->checkoutAccessories()->create())
        ->from(route('accessories.checkout.show', $accessory))
        ->post(route('accessories.checkout.store', $accessory), [
            'assigned_user' => $user->id,
            'checkout_to_type' => 'user',
            'checkout_qty' => 3,
            'note' => 'oh hi there',
        ]);

    expect($accessory->checkouts()->where('assigned_type', User::class)->where('assigned_to', $user->id)->count() > 0)->toBeTrue();

    $this->assertDatabaseHas('action_logs', [
        'action_type' => 'checkout',
        'target_id' => $user->id,
        'target_type' => User::class,
        'item_id' => $accessory->id,
        'item_type' => Accessory::class,
        'note' => 'oh hi there',
    ]);
    $this->assertHasTheseActionLogs($accessory, ['create', 'checkout']);
});

test('accessory can be checked out to location with quantity', function () {
    $accessory = Accessory::factory()->create(['qty'=>5]);
    $location = Location::factory()->create();

    $this->actingAs(User::factory()->checkoutAccessories()->create())
        ->from(route('accessories.checkout.show', $accessory))
        ->post(route('accessories.checkout.store', $accessory), [
            'assigned_location' => $location->id,
            'checkout_to_type' => 'location',
            'checkout_qty' => 3,
            'note' => 'oh hi there',
        ]);

    expect($accessory->checkouts()->where('assigned_type', Location::class)->where('assigned_to', $location->id)->count() > 0)->toBeTrue();

    $this->assertDatabaseHas('action_logs', [
        'action_type' => 'checkout',
        'target_id' => $location->id,
        'target_type' => Location::class,
        'item_id' => $accessory->id,
        'item_type' => Accessory::class,
        'note' => 'oh hi there',
    ]);
    $this->assertHasTheseActionLogs($accessory, ['create', 'checkout']);
});

test('accessory can be checked out to asset with quantity', function () {
    $accessory = Accessory::factory()->create(['qty'=>5]);
    $asset = Asset::factory()->create();

    $this->actingAs(User::factory()->checkoutAccessories()->create())
        ->from(route('accessories.checkout.show', $accessory))
        ->post(route('accessories.checkout.store', $accessory), [
            'assigned_asset' => $asset->id,
            'checkout_to_type' => 'asset',
            'checkout_qty' => 3,
            'note' => 'oh hi there',
        ]);

    expect($accessory->checkouts()->where('assigned_type', Asset::class)->where('assigned_to', $asset->id)->count() > 0)->toBeTrue();

    $this->assertDatabaseHas('action_logs', [
        'action_type' => 'checkout',
        'target_id' => $asset->id,
        'target_type' => Asset::class,
        'item_id' => $accessory->id,
        'item_type' => Accessory::class,
        'note' => 'oh hi there',
    ]);
    $this->assertHasTheseActionLogs($accessory, ['create', 'checkout']);
});

test('user sent notification upon checkout', function () {
    Mail::fake();

    $accessory = Accessory::factory()->requiringAcceptance()->create();
    $user = User::factory()->create();

    $this->actingAs(User::factory()->checkoutAccessories()->create())
        ->from(route('accessories.checkout.show', $accessory))
        ->post(route('accessories.checkout.store', $accessory), [
            'assigned_user' => $user->id,
            'checkout_to_type' => 'user',
        ]);

    Mail::assertSent(CheckoutAccessoryMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

test('action log created upon checkout', function () {
    $accessory = Accessory::factory()->create();
    $actor = User::factory()->checkoutAccessories()->create();
    $user = User::factory()->create();

    $this->actingAs($actor)
        ->from(route('accessories.checkout.show', $accessory))
        ->post(route('accessories.checkout.store', $accessory), [
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
        'created_by' => $actor->id,
        'note' => 'oh hi there',
    ])->count())->toEqual(1, 'Log entry either does not exist or there are more than expected');
    $this->assertHasTheseActionLogs($accessory, ['create', 'checkout']);
});

test('accessory checkout page post is redirected if redirect selection is index', function () {
    $accessory = Accessory::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('accessories.index'))
        ->post(route('accessories.checkout.store', $accessory), [
            'assigned_user' => User::factory()->create()->id,
            'checkout_to_type' => 'user',
            'redirect_option' => 'index',
            'assigned_qty' => 1,
        ])
        ->assertStatus(302)
        ->assertRedirect(route('accessories.index'));
});

test('accessory checkout page post is redirected if redirect selection is item', function () {
    $accessory = Accessory::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('accessories.index'))
        ->post(route('accessories.checkout.store' , $accessory), [
            'assigned_user' => User::factory()->create()->id,
            'checkout_to_type' => 'user',
            'redirect_option' => 'item',
            'assigned_qty' => 1,
        ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accessories.show', $accessory));
});

test('accessory checkout page post is redirected if redirect selection is target', function () {
    $user = User::factory()->create();
    $accessory = Accessory::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('accessories.index'))
        ->post(route('accessories.checkout.store' , $accessory), [
            'assigned_user' => $user->id,
            'checkout_to_type' => 'user',
            'redirect_option' => 'target',
            'assigned_qty' => 1,
        ])
        ->assertStatus(302)
        ->assertRedirect(route('users.show', $user));
});
