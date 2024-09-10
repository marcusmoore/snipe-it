<?php

use App\Models\Category;
use App\Models\Asset;
use App\Models\User;

test('permission required to store category', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('categories.store'), [
            'name' => 'Test Category',
            'category_type' => 'asset'
        ])
        ->assertStatus(403)
        ->assertForbidden();
});

test('user can create categories', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->post(route('categories.store'), [
            'name' => 'Test Category',
            'category_type' => 'asset'
        ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.index'));

    expect(Category::where('name', 'Test Category')->exists())->toBeTrue();
});

test('user can edit asset category', function () {
    $category = Category::factory()->forAssets()->create(['name' => 'Test Category']);
    expect(Category::where('name', 'Test Category')->exists())->toBeTrue();

    $response = $this->actingAs(User::factory()->superuser()->create())
        ->put(route('categories.update', ['category' => $category]), [
            'name' => 'Test Category Edited',
        ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect(Category::where('name', 'Test Category Edited')->exists())->toBeTrue();
});

test('user can change category type if no assets associated', function () {
    $category = Category::factory()->forAssets()->create(['name' => 'Test Category']);
    expect(Category::where('name', 'Test Category')->exists())->toBeTrue();

    $response = $this->actingAs(User::factory()->superuser()->create())
        ->from(route('categories.edit', ['category' => $category->id]))
        ->put(route('categories.update', ['category' => $category]), [
            'name' => 'Test Category Edited',
            'category_type' => 'accessory',
        ])
        ->assertSessionHasNoErrors()
        ->assertStatus(302)
        ->assertRedirect(route('categories.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect(Category::where('name', 'Test Category Edited')->exists())->toBeTrue();
});

test('user cannot change category type if assets are associated', function () {
    Asset::factory()->count(5)->laptopMbp()->create();
    $category = Category::where('name', 'Laptops')->first();

    $response = $this->actingAs(User::factory()->superuser()->create())
        ->from(route('categories.edit', ['category' => $category->id]))
        ->put(route('categories.update', ['category' => $category]), [
            'name' => 'Test Category Edited',
            'category_type' => 'accessory',
        ])
        ->assertSessionHasErrors(['category_type'])
        ->assertInvalid(['category_type'])
        ->assertStatus(302)
        ->assertRedirect(route('categories.edit', ['category' => $category->id]));

    $this->followRedirects($response)->assertSee(trans('general.error'));
    expect(Category::where('name', 'Test Category Edited')->exists())->toBeFalse();
});
