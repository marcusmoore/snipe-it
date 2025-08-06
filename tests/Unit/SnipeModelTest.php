<?php

use App\Models\SnipeModel;

test('sets purchase dates appropriately', function () {
    $c = new SnipeModel;
    $c->purchase_date = '';
    expect($c->purchase_date === null)->toBeTrue();
    $c->purchase_date = '2016-03-25 12:35:50';
    expect($c->purchase_date === '2016-03-25 12:35:50')->toBeTrue();
});

test('sets purchase costs appropriately', function () {
    $c = new SnipeModel;
    $c->purchase_cost = '0.00';
    expect($c->purchase_cost === null)->toBeTrue();
    $c->purchase_cost = '9.54';
    expect($c->purchase_cost === 9.54)->toBeTrue();
    $c->purchase_cost = '9.50';
    expect($c->purchase_cost === 9.5)->toBeTrue();
});

test('nulls blank location ids but not others', function () {
    $c = new SnipeModel;
    $c->location_id = '';
    expect($c->location_id === null)->toBeTrue();
    $c->location_id = '5';
    expect($c->location_id == 5)->toBeTrue();
});

test('nulls blank categories but not others', function () {
    $c = new SnipeModel;
    $c->category_id = '';
    expect($c->category_id === null)->toBeTrue();
    $c->category_id = '1';
    expect($c->category_id == 1)->toBeTrue();
});

test('nulls blank suppliers but not others', function () {
    $c = new SnipeModel;
    $c->supplier_id = '';
    expect($c->supplier_id === null)->toBeTrue();
    $c->supplier_id = '4';
    expect($c->supplier_id == 4)->toBeTrue();
});

test('nulls blank depreciations but not others', function () {
    $c = new SnipeModel;
    $c->depreciation_id = '';
    expect($c->depreciation_id === null)->toBeTrue();
    $c->depreciation_id = '4';
    expect($c->depreciation_id == 4)->toBeTrue();
});

test('nulls blank manufacturers but not others', function () {
    $c = new SnipeModel;
    $c->manufacturer_id = '';
    expect($c->manufacturer_id === null)->toBeTrue();
    $c->manufacturer_id = '4';
    expect($c->manufacturer_id == 4)->toBeTrue();
});
