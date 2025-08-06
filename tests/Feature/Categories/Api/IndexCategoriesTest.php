<?php

use App\Models\Category;
use App\Models\User;

test('viewing category index requires permission', function () {
    $this->actingAsForApi(User::factory()->create())
        ->getJson(route('api.categories.index'))
        ->assertForbidden();
});

test('category index returns expected search results', function () {
    Category::factory()->count(10)->create();
    Category::factory()->count(1)->forAssets()->create(['name' => 'My Test Category']);

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(
            route('api.categories.index', [
                'search' => 'My Test Category',
                'sort' => 'name',
                'order' => 'asc',
                'offset' => '0',
                'limit' => '20',
            ]))
        ->assertOk()
        ->assertJsonStructure([
            'total',
            'rows',
        ])
        ->assertJson([
            'total' => 1,
        ]);
});

test('category index returns expected categories', function () {
    $this->markTestIncomplete('Not sure why the category factory is generating one more than expected here.');
    Category::factory()->count(3)->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(
            route('api.categories.index', [
                'sort' => 'id',
                'order' => 'asc',
                'offset' => '0',
                'limit' => '20',
            ]))
        ->assertOk()
        ->assertJsonStructure([
            'total',
            'rows',
        ])
        ->assertJson([
            'total' => 3,
        ]);
});
