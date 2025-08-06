<?php

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Consumable;
use App\Models\LicenseSeat;
use App\Models\User;
use App\Models\Actionlog;

test('assets are transferred on user merge', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user_to_merge_into = User::factory()->create();

    Asset::factory()->count(3)->assignedToUser($user1)->create();
    Asset::factory()->count(3)->assignedToUser($user2)->create();
    Asset::factory()->count(3)->assignedToUser($user_to_merge_into)->create();

    $response = $this->actingAs(User::factory()->editUsers()->viewUsers()->create())
        ->post(route('users.merge.save', $user1->id),
            [
                'ids_to_merge' => [$user1->id, $user2->id],
                'merge_into_id' => $user_to_merge_into->id
            ])
        ->assertStatus(302)
        ->assertRedirect(route('users.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect($user_to_merge_into->refresh()->assets->count())->toEqual(9);
    expect($user1->refresh()->assets->count())->toEqual(0);
    expect($user2->refresh()->assets->count())->toEqual(0);
});

test('licenses are transferred on user merge', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user_to_merge_into = User::factory()->create();

    LicenseSeat::factory()->count(3)->create(['assigned_to' => $user1->id]);
    LicenseSeat::factory()->count(3)->create(['assigned_to' => $user2->id]);
    LicenseSeat::factory()->count(3)->create(['assigned_to' => $user_to_merge_into->id]);

    expect($user_to_merge_into->refresh()->licenses->count())->toEqual(3);

    $response = $this->actingAs(User::factory()->editUsers()->viewUsers()->create())
        ->post(route('users.merge.save', $user1->id),
            [
                'ids_to_merge' => [$user1->id, $user2->id],
                'merge_into_id' => $user_to_merge_into->id
            ])
        ->assertStatus(302)
        ->assertRedirect(route('users.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect($user_to_merge_into->refresh()->licenses->count())->toEqual(9);
    expect($user1->refresh()->licenses->count())->toEqual(0);
    expect($user2->refresh()->licenses->count())->toEqual(0);
});

test('accessories transferred on user merge', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user_to_merge_into = User::factory()->create();

    Accessory::factory()->count(3)->checkedOutToUser($user1)->create();
    Accessory::factory()->count(3)->checkedOutToUser($user2)->create();
    Accessory::factory()->count(3)->checkedOutToUser($user_to_merge_into)->create();

    expect($user_to_merge_into->refresh()->accessories->count())->toEqual(3);

    $response = $this->actingAs(User::factory()->editUsers()->viewUsers()->create())
        ->post(route('users.merge.save', $user1->id),
            [
                'ids_to_merge' => [$user1->id, $user2->id],
                'merge_into_id' => $user_to_merge_into->id
            ])
        ->assertStatus(302)
        ->assertRedirect(route('users.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect($user_to_merge_into->refresh()->accessories->count())->toEqual(9);
    expect($user1->refresh()->accessories->count())->toEqual(0);
    expect($user2->refresh()->accessories->count())->toEqual(0);
});

test('consumables transferred on user merge', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user_to_merge_into = User::factory()->create();

    Consumable::factory()->count(3)->checkedOutToUser($user1)->create();
    Consumable::factory()->count(3)->checkedOutToUser($user2)->create();
    Consumable::factory()->count(3)->checkedOutToUser($user_to_merge_into)->create();

    expect($user_to_merge_into->refresh()->consumables->count())->toEqual(3);

    $response = $this->actingAs(User::factory()->editUsers()->viewUsers()->create())
        ->post(route('users.merge.save', $user1->id),
            [
                'ids_to_merge' => [$user1->id, $user2->id],
                'merge_into_id' => $user_to_merge_into->id
            ])
        ->assertStatus(302)
        ->assertRedirect(route('users.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect($user_to_merge_into->refresh()->consumables->count())->toEqual(9);
    expect($user1->refresh()->consumables->count())->toEqual(0);
    expect($user2->refresh()->consumables->count())->toEqual(0);
});

test('files are transferred on user merge', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user_to_merge_into = User::factory()->create();

    Actionlog::factory()->count(3)->filesUploaded()->create(['item_id' => $user1->id]);
    Actionlog::factory()->count(3)->filesUploaded()->create(['item_id' => $user2->id]);
    Actionlog::factory()->count(3)->filesUploaded()->create(['item_id' => $user_to_merge_into->id]);

    expect($user_to_merge_into->refresh()->uploads->count())->toEqual(3);

    $response = $this->actingAs(User::factory()->editUsers()->viewUsers()->create())
        ->post(route('users.merge.save', $user1->id),
            [
                'ids_to_merge' => [$user1->id, $user2->id],
                'merge_into_id' => $user_to_merge_into->id
            ])
        ->assertStatus(302)
        ->assertRedirect(route('users.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect($user_to_merge_into->refresh()->uploads->count())->toEqual(9);
    expect($user1->refresh()->uploads->count())->toEqual(0);
    expect($user2->refresh()->uploads->count())->toEqual(0);
});

test('acceptances are transferred on user merge', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user_to_merge_into = User::factory()->create();

    Actionlog::factory()->count(3)->acceptedSignature()->create(['target_id' => $user1->id]);
    Actionlog::factory()->count(3)->acceptedSignature()->create(['target_id' => $user2->id]);
    Actionlog::factory()->count(3)->acceptedSignature()->create(['target_id' => $user_to_merge_into->id]);

    expect($user_to_merge_into->refresh()->acceptances->count())->toEqual(3);

    $response = $this->actingAs(User::factory()->editUsers()->viewUsers()->create())
        ->post(route('users.merge.save', $user1->id),
            [
                'ids_to_merge' => [$user1->id, $user2->id],
                'merge_into_id' => $user_to_merge_into->id
            ])
        ->assertStatus(302)
        ->assertRedirect(route('users.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect($user_to_merge_into->refresh()->acceptances->count())->toEqual(9);
    expect($user1->refresh()->acceptances->count())->toEqual(0);
    expect($user2->refresh()->acceptances->count())->toEqual(0);
});

test('user update history is transferred on user merge', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user_to_merge_into = User::factory()->create();

    Actionlog::factory()->count(3)->userUpdated()->create(['target_id' => $user1->id, 'item_id' => $user1->id]);
    Actionlog::factory()->count(3)->userUpdated()->create(['target_id' => $user2->id, 'item_id' => $user2->id]);
    Actionlog::factory()->count(3)->userUpdated()->create(['target_id' => $user_to_merge_into->id, 'item_id' => $user_to_merge_into->id]);

    expect($user_to_merge_into->refresh()->userlog->count())->toEqual(3);

    $response = $this->actingAs(User::factory()->editUsers()->viewUsers()->create())
        ->post(route('users.merge.save', $user1->id),
            [
                'ids_to_merge' => [$user1->id, $user2->id],
                'merge_into_id' => $user_to_merge_into->id
            ])
        ->assertStatus(302)
        ->assertRedirect(route('users.index'));

    $this->followRedirects($response)->assertSee('Success');

    // This needs to be 2 more than the otherwise expected because the merge action itself is logged for the two merging users
    expect($user_to_merge_into->refresh()->userlog->count())->toEqual(11);
    expect($user1->refresh()->userlog->count())->toEqual(2);
    expect($user2->refresh()->userlog->count())->toEqual(2);
});
