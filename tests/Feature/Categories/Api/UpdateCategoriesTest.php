<?php

use App\Models\Category;
use App\Models\User;

test('can update category via patch without category type', function () {
    $category = Category::factory()->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.categories.update', $category), [
            'name' => 'Test Category',
            'eula_text' => 'Test EULA',
        ])
        ->assertOk()
        ->assertStatusMessageIs('success')
        ->assertStatus(200)
        ->json();

    //dd($response);
    $category->refresh();
    expect($category->name)->toEqual('Test Category', 'Name was not updated');
    expect($category->eula_text)->toEqual('Test EULA', 'EULA was not updated');
});

test('cannot update category via patch with category type', function () {
    $category = Category::factory()->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->patchJson(route('api.categories.update', $category), [
            'name' => 'Test Category',
            'eula_text' => 'Test EULA',
            'category_type' => 'accessory',
        ])
        ->assertOk()
        ->assertStatusMessageIs('error')
        ->assertStatus(200)
        ->json();

    $category->refresh();
    $this->assertNotEquals('Test Category', $category->name, 'Name was not updated');
    $this->assertNotEquals('Test EULA', $category->eula_text, 'EULA was not updated');
    $this->assertNotEquals('accessory', $category->category_type, 'EULA was not updated');
});
