<?php

use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;

test('requires permission', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    $customFieldset = CustomFieldset::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->deleteJson(route('api.fieldsets.destroy', $customFieldset))
        ->assertForbidden();

    $this->assertDatabaseHas('custom_fieldsets', ['id' => $customFieldset->id]);
});

test('cannot delete custom fieldset with associated fields', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    $customField = CustomField::factory()->create();
    $customFieldset = CustomFieldset::factory()->create();

    $customField->fieldset()->attach($customFieldset, ['order' => 1, 'required' => 'false']);

    $this->actingAsForApi(User::factory()->deleteCustomFieldsets()->create())
        ->deleteJson(route('api.fieldsets.destroy', $customFieldset))
        ->assertStatusMessageIs('error');

    $this->assertDatabaseHas('custom_fieldsets', ['id' => $customFieldset->id]);
});

test('cannot delete custom fieldset with associated models', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    $customFieldset = CustomFieldset::factory()->hasModels()->create();

    $this->actingAsForApi(User::factory()->deleteCustomFieldsets()->create())
        ->deleteJson(route('api.fieldsets.destroy', $customFieldset))
        ->assertStatusMessageIs('error');

    $this->assertDatabaseHas('custom_fieldsets', ['id' => $customFieldset->id]);
});

test('can delete custom fieldsets', function () {
    $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

    $customFieldset = CustomFieldset::factory()->create();

    $this->actingAsForApi(User::factory()->deleteCustomFieldsets()->create())
        ->deleteJson(route('api.fieldsets.destroy', $customFieldset))
        ->assertStatusMessageIs('success');

    $this->assertDatabaseMissing('custom_fieldsets', ['id' => $customFieldset->id]);
});
