<?php

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\User;

test('permission required to create categories', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('categories.store'), [
            'name' => 'Test Category',
            'category_type' => 'asset',
        ])
        ->assertForbidden();
});

test('user can create categories', function () {
    expect(Category::where('name', 'Test Category')->exists())->toBeFalse();

    $this->actingAs(User::factory()->superuser()->create())
        ->post(route('categories.store'), [
            'name' => 'Test Category',
            'category_type' => 'asset'
        ])
        ->assertRedirect(route('categories.index'));

    expect(Category::where('name', 'Test Category')->exists())->toBeTrue();
});

test('user cannot create categories with invalid type', function () {
    expect(Category::where('name', 'Test Category')->exists())->toBeFalse();

    $this->actingAs(User::factory()->superuser()->create())
        ->from(route('categories.create'))
        ->post(route('categories.store'), [
            'name' => 'Test Category',
            'category_type' => 'invalid'
        ])
        ->assertRedirect(route('categories.create'));

    expect(Category::where('name', 'Test Category')->exists())->toBeFalse();
});
