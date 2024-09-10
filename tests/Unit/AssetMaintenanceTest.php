<?php

use App\Models\AssetMaintenance;

test('zeros out warranty if blank', function () {
    $c = new AssetMaintenance;
    $c->is_warranty = '';
    expect($c->is_warranty === 0)->toBeTrue();
    $c->is_warranty = '4';
    expect($c->is_warranty == 4)->toBeTrue();
});

test('sets costs appropriately', function () {
    $c = new AssetMaintenance();
    $c->cost = '0.00';
    expect($c->cost === null)->toBeTrue();
    $c->cost = '9.54';
    expect($c->cost === 9.54)->toBeTrue();
    $c->cost = '9.50';
    expect($c->cost === 9.5)->toBeTrue();
});

test('nulls out notes if blank', function () {
    $c = new AssetMaintenance;
    $c->notes = '';
    expect($c->notes === null)->toBeTrue();
    $c->notes = 'This is a long note';
    expect($c->notes === 'This is a long note')->toBeTrue();
});

test('nulls out completion date if blank or invalid', function () {
    $c = new AssetMaintenance;
    $c->completion_date = '';
    expect($c->completion_date === null)->toBeTrue();
    $c->completion_date = '0000-00-00';
    expect($c->completion_date === null)->toBeTrue();
});
