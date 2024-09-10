<?php

use App\Models\AssetModel;
use App\Models\Department;
use App\Models\Category;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('requires permission to create department', function () {
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.departments.store'))
        ->assertForbidden();
});
