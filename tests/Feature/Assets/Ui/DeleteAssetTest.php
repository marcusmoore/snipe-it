<?php

use App\Events\CheckoutableCheckedIn;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

pest()->group('assets', 'ui');

test('permission needed to delete asset', function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('hardware.destroy', Asset::factory()->create()))
        ->assertForbidden();
});

test('can delete asset', function () {
    $asset = Asset::factory()->create();

    $this->actingAs(User::factory()->deleteAssets()->create())
        ->delete(route('hardware.destroy', $asset))
        ->assertRedirectToRoute('hardware.index')
        ->assertSessionHas('success');

    $this->assertSoftDeleted($asset);
});

test('action log entry made when asset deleted', function () {
    $actor = User::factory()->deleteAssets()->create();

    $asset = Asset::factory()->create();

    $this->actingAs($actor)->delete(route('hardware.destroy', $asset));

    $this->assertDatabaseHas('action_logs', [
        'created_by' => $actor->id,
        'action_type' => 'delete',
        'target_id' => null,
        'target_type' => null,
        'item_type' => Asset::class,
        'item_id' => $asset->id,
    ]);
});

test('action logs action date is populated when asset deleted', function () {
    $actor = User::factory()->deleteAssets()->create();

    $asset = Asset::factory()->create();

    $this->actingAs($actor)->delete(route('hardware.destroy', $asset));

    $asset->refresh();

    $this->assertDatabaseHas('action_logs', [
        'action_date' => $asset->updated_at,
        'created_at' => $asset->updated_at,
        'created_by' => $actor->id,
        'action_type' => 'delete',
        'target_id' => null,
        'target_type' => null,
        'item_type' => Asset::class,
        'item_id' => $asset->id,
    ]);
});

test('asset is checked in when deleted', function () {
    Event::fake();

    $assignedUser = User::factory()->create();
    $asset = Asset::factory()->assignedToUser($assignedUser)->create();

    expect($assignedUser->assets->contains($asset))->toBeTrue();

    $this->actingAs(User::factory()->deleteAssets()->create())
        ->delete(route('hardware.destroy', $asset));

    expect($assignedUser->fresh()->assets->contains($asset))->toBeFalse('Asset still assigned to user after deletion');

    $asset->refresh();
    expect($asset->assigned_to)->toBeNull();
    expect($asset->assigned_type)->toBeNull();

    Event::assertDispatched(CheckoutableCheckedIn::class);
});

test('image is deleted when asset deleted', function () {
    Storage::fake('public');

    $asset = Asset::factory()->create(['image' => 'image.jpg']);

    Storage::disk('public')->put('assets/image.jpg', 'content');

    Storage::disk('public')->assertExists('assets/image.jpg');

    $this->actingAs(User::factory()->deleteAssets()->create())
        ->delete(route('hardware.destroy', $asset));

    Storage::disk('public')->assertMissing('assets/image.jpg');
});
