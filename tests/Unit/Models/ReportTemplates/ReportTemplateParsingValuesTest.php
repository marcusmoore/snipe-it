<?php

use App\Models\Department;
use App\Models\Location;
use App\Models\ReportTemplate;

test('parsing values on non existent report template', function () {
    $unsavedTemplate = new ReportTemplate;

    // checkmarkValue() should be "checked" (1) by default
    expect($unsavedTemplate->checkmarkValue('is_a_checkbox_field'))->toEqual('1');

    // radioValue() defaults to false but can be overridden
    expect($unsavedTemplate->radioValue('value_on_unsaved_template', 'can_be_anything'))->toBeFalse();
    expect($unsavedTemplate->radioValue('value_on_unsaved_template', 'can_be_anything', true))->toBeTrue();

    // selectValue() should be null by default
    expect($unsavedTemplate->selectValue('value_on_unsaved_template'))->toBeNull();
    expect($unsavedTemplate->selectValue('value_on_unsaved_template'))->toBeNull(Location::class);

    // selectValues() should be an empty array by default
    expect($unsavedTemplate->selectValues('value_on_unsaved_template'))->toBeArray();
    expect($unsavedTemplate->selectValues('value_on_unsaved_template'))->toBeEmpty();
    expect($unsavedTemplate->selectValues('value_on_unsaved_template'))->toBeEmpty(Location::class);

    // textValue() should be an empty string by default
    expect($unsavedTemplate->selectValue('value_on_unsaved_template'))->toEqual('');
});

test('parsing checkmark value', function () {
    $template = ReportTemplate::factory()->create([
        'options' => [
            'is_a_checkbox_field' => '1',
            // This shouldn't happen since unchecked inputs are
            // not submitted, but we should handle it anyway
            'is_checkbox_field_with_zero' => '0',
        ],
    ]);

    expect($template->checkmarkValue('is_a_checkbox_field'))->toEqual('1');
    expect($template->checkmarkValue('non_existent_key'))->toEqual('0');
    expect($template->checkmarkValue('is_checkbox_field_with_zero'))->toEqual('0');
    expect((new ReportTemplate)->checkmarkValue('non_existent_key_that_is_overwritten_to_default_to_zero', '0'))->toEqual('0');
});

test('parsing text value', function () {
    $template = ReportTemplate::factory()->create([
        'options' => [
            'is_a_text_field' => 'some text',
        ],
    ]);

    expect($template->textValue('is_a_text_field'))->toEqual('some text');
    expect($template->textValue('non_existent_key'))->toEqual('');

    expect((new ReportTemplate)->textValue('is_a_text_field'))->toEqual('');
    expect((new ReportTemplate)->textValue('non_existent_key', 'my fallback'))->toEqual('my fallback');
});

test('parsing radio value', function () {
    $template = ReportTemplate::factory()->create([
        'options' => ['property_that_exists' => '1'],
    ]);

    expect($template->radioValue('property_that_exists', '1'))->toBeTrue();

    // check non-existent key returns false
    expect($template->radioValue('non_existent_property', 'doesnt_matter'))->toBeFalse();

    // check can return fallback value
    expect($template->radioValue('non_existent_property', 'doesnt_matter', true))->toBeTrue();
});

test('parsing select value', function () {
    $template = ReportTemplate::factory()->create([
        'options' => [
            'is_a_text_field_as_well' => '4',
            'contains_a_null_value' => null,
        ],
    ]);

    expect($template->selectValue('is_a_text_field_as_well'))->toEqual('4');
    expect($template->selectValue('non_existent_key'))->toEqual('');
    expect($template->selectValue('contains_a_null_value'))->toBeNull();
});

test('parsing select values', function () {
    $template = ReportTemplate::factory()->create([
        'options' => [
            'an_array' => ['2', '3', '4'],
            'an_empty_array' => [],
            'an_array_containing_null' => [null],
        ],
    ]);

    expect($template->selectValues('an_array'))->toEqual(['2', '3', '4']);
    expect($template->selectValues('an_empty_array'))->toEqual([]);
    expect($template->selectValues('an_array_containing_null'))->toEqual([null]);
    expect($template->selectValues('non_existent_key'))->toEqual([]);
});

test('select value does not include deleted or non existent models', function () {
    [$locationA, $locationB] = Location::factory()->count(2)->create();
    $invalidId = 10000;

    $templateWithValidId = ReportTemplate::factory()->create([
        'options' => ['single_value' => $locationA->id],
    ]);

    $templateWithDeletedId = ReportTemplate::factory()->create([
        'options' => ['single_value' => $locationB->id],
    ]);
    $locationB->delete();

    $templateWithInvalidId = ReportTemplate::factory()->create([
        'options' => ['single_value' => $invalidId],
    ]);

    expect($templateWithValidId->selectValue('single_value', Location::class))->toEqual($locationA->id);

    expect($templateWithDeletedId->selectValue('single_value', Location::class))->toBeNull();
    expect($templateWithInvalidId->selectValue('single_value', Location::class))->toBeNull();
    expect((new ReportTemplate)->selectValue('value_on_unsaved_template', Location::class))->toBeNull();
});

test('select values do not include deleted or non existent models', function () {
    [$locationA, $locationB] = Location::factory()->count(2)->create();
    $invalidId = 10000;

    $template = ReportTemplate::factory()->create([
        'options' => [
            'array_of_ids' => [
                $locationA->id,
                $locationB->id,
                $invalidId,
            ],
        ],
    ]);

    $locationB->delete();

    $parsedValues = $template->selectValues('array_of_ids', Location::class);

    expect($parsedValues)->toContain($locationA->id);
    expect($parsedValues)->not->toContain($locationB->id);
    expect($parsedValues)->not->toContain($invalidId);
});

test('gracefully handles single select becoming multi select', function () {
    $department = Department::factory()->create();

    $templateWithValue = ReportTemplate::factory()->create([
        'options' => ['single_value' => 'a string'],
    ]);

    $templateWithModelId = ReportTemplate::factory()->create([
        'options' => ['by_dept_id' => $department->id],
    ]);

    // If nothing is selected for a single select then it is stored
    // as null and should be returned as an empty array.
    $templateWithNull = ReportTemplate::factory()->create([
        'options' => ['by_dept_id' => null],
    ]);

    expect($templateWithValue->selectValues('single_value'))->toEqual(['a string']);
    expect($templateWithModelId->selectValues('by_dept_id', Department::class))->toContain($department->id);
    expect($templateWithNull->selectValues('by_dept_id'))->toEqual([]);
});

test('gracefully handles multi select becoming single select by selecting the first value', function () {
    [$departmentA, $departmentB] = Department::factory()->count(2)->create();

    // Given report templates saved with a property that is an array of values
    $templateWithValuesInArray = ReportTemplate::factory()->create([
        'options' => ['array_of_values' => [3, 'a string']],
    ]);

    $templateWithModelIdsInArray = ReportTemplate::factory()->create([
        'options' => ['array_of_model_ids' => [$departmentA->id, $departmentB->id]],
    ]);

    expect($templateWithValuesInArray->selectValue('array_of_values'))->toEqual(3);
    expect($templateWithModelIdsInArray->selectValue('array_of_model_ids', Department::class))->toEqual($departmentA->id);
});
