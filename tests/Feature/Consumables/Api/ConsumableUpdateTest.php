<?php

use App\Models\Consumable;
use App\Models\Category;
use App\Models\User;

test('can update consumable via patch without category type', function () {
    $consumable = Consumable::factory()->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.consumables.update', $consumable), [
            'name' => 'Test Consumable',
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->assertStatus(200)
        ->json();

    $consumable->refresh();
    expect($consumable->name)->toEqual('Test Consumable', 'Name was not updated');
});

test('cannot update consumable via patch with invalid category type', function () {
    $category = Category::factory()->create(['category_type' => 'asset']);
    $consumable = Consumable::factory()->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.consumables.update', $consumable), [
            'name' => 'Test Consumable',
            'category_id' => $category->id,
        ])
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->assertStatus(200)
        ->json();

    $category->refresh();
    $this->assertNotEquals('Test Consumable', $consumable->name, 'Name was not updated');
    $this->assertNotEquals('consumable', $consumable->category_id, 'Category was not updated');
});
