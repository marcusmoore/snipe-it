<?php

use App\Models\Asset;
use App\Models\Labels\FieldOption;
use App\Models\User;

test('it displays assigned to properly', function () {
    // "assignedTo" is a "special" value that can be used in the new label engine
    $fieldOption = FieldOption::fromString('Assigned To=assignedTo');

    $asset = Asset::factory()
        ->assignedToUser(User::factory()->create(['first_name' => 'Luke', 'last_name' => 'Skywalker']))
        ->create();

    expect($fieldOption->getValue($asset))->toEqual('Luke Skywalker');

    // If the "assignedTo" relationship was eager loaded then the way to get the
    // relationship changes from $asset->assignedTo to $asset->assigned.
    expect($fieldOption->getValue(Asset::with('assignedTo')->find($asset->id)))->toEqual('Luke Skywalker');
});
