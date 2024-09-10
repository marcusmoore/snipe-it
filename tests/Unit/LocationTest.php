<?php

use App\Models\Location;

test('passes if not self parent', function () {
    $a = Location::factory()->make([
        'name' => 'Test Location',
        'id' => 1,
        'parent_id' => Location::factory()->create(['id' => 10])->id,
    ]);

    expect($a->isValid())->toBeTrue();
});

test('fails if self parent', function () {
    $a = Location::factory()->make([
        'name' => 'Test Location',
        'id' => 1,
        'parent_id' => 1,
    ]);

    expect($a->isValid())->toBeFalse();
    $this->assertStringContainsString(trans('validation.non_circular', ['attribute' => 'parent id']), $a->getErrors());
});
