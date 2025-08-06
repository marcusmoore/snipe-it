<?php

use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;

test('requires permission', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    $customField = CustomField::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->deleteJson(route('api.customfields.destroy', $customField))
        ->assertForbidden();

    $this->assertDatabaseHas('custom_fields', ['id' => $customField->id]);
});

test('custom fields cannot be deleted if they have associated fieldsets', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    $customField = CustomField::factory()->create();
    $customFieldset = CustomFieldset::factory()->create();

    $customField->fieldset()->attach($customFieldset, ['order' => 1, 'required' => 'false']);

    $this->actingAsForApi(User::factory()->deleteCustomFields()->create())
        ->deleteJson(route('api.customfields.destroy', $customField))
        ->assertStatusMessageIs('error');

    $this->assertDatabaseHas('custom_fields', ['id' => $customField->id]);
});

test('custom fields can be deleted', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    $customField = CustomField::factory()->create();

    $this->actingAsForApi(User::factory()->deleteCustomFields()->create())
        ->deleteJson(route('api.customfields.destroy', $customField))
        ->assertStatusMessageIs('success');

    $this->assertDatabaseMissing('custom_fields', ['id' => $customField->id]);
});
