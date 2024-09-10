<?php

use App\Models\Component;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('checking in component requires correct permission', function () {
    $component = Component::factory()->checkedOutToAsset()->create();

    $componentAsset = DB::table('components_assets')->where('component_id', $component->id)->first();

    $this->actingAs(User::factory()->create())
        ->post(route('components.checkin.store', [
            'componentID' => $componentAsset->id,
        ]))
        ->assertForbidden();
});

test('component checkin page post is redirected if redirect selection is index', function () {
    $component = Component::factory()->checkedOutToAsset()->create();

    $componentAsset = DB::table('components_assets')->where('component_id', $component->id)->first();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('components.index'))
        ->post(route('components.checkin.store', [
            'componentID' => $componentAsset->id,
        ]), [
            'redirect_option' => 'index',
            'checkin_qty' => 1,
        ])
        ->assertStatus(302)
        ->assertRedirect(route('components.index'));
});

test('component checkin page post is redirected if redirect selection is item', function () {
    $component = Component::factory()->checkedOutToAsset()->create();

    $componentAsset = DB::table('components_assets')->where('component_id', $component->id)->first();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('components.index'))
        ->post(route('components.checkin.store', [
            'componentID' => $componentAsset->id,
        ]), [
            'redirect_option' => 'item',
            'checkin_qty' => 1,
        ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('components.show', ['component' => $component->id]));
});
