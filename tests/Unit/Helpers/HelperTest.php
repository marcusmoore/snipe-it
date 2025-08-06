<?php

use App\Helpers\Helper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

test('default chart colors method handles high values', function () {
    expect(Helper::defaultChartColors(1000))->toBeString();
});

test('default chart colors method handles negative numbers', function () {
    expect(Helper::defaultChartColors(-1))->toBeString();
});

test('parse currency method', function () {
    $this->settings->set(['default_currency' => 'USD']);
    expect(Helper::ParseCurrency('USD 12.34'))->toBe(12.34);

    $this->settings->set(['digit_separator' => '1.234,56']);
    expect(Helper::ParseCurrency('12,34'))->toBe(12.34);
});

test('get redirect option method', function () {
    $test_data = [
        'Option target: redirect for user assigned to ' => [
            'request' => (object) ['assigned_user' => 22],
            'id' => 1,
            'checkout_to_type' => 'user',
            'redirect_option' => 'target',
            'table' => 'Assets',
            'route' => route('users.show', 22),
        ],
        'Option target: redirect location assigned to ' => [
            'request' => (object) ['assigned_location' => 10],
            'id' => 2,
            'checkout_to_type' => 'location',
            'redirect_option' => 'target',
            'table' => 'Locations',
            'route' => route('locations.show', 10),
        ],
        'Option target: redirect back to asset assigned to ' => [
            'request' => (object) ['assigned_asset' => 101],
            'id' => 3,
            'checkout_to_type' => 'asset',
            'redirect_option' => 'target',
            'table' => 'Assets',
            'route' => route('hardware.show', 101),
        ],
        'Option item: redirect back to asset ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => 999,
            'checkout_to_type' => null,
            'redirect_option' => 'item',
            'table' => 'Assets',
            'route' => route('hardware.show', 999),
        ],
        'Option index: redirect back to asset index ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => null,
            'checkout_to_type' => null,
            'redirect_option' => 'index',
            'table' => 'Assets',
            'route' => route('hardware.index'),
        ],

        'Option item: redirect back to user ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => 999,
            'checkout_to_type' => null,
            'redirect_option' => 'item',
            'table' => 'Users',
            'route' => route('users.show', 999),
        ],

        'Option index: redirect back to user index ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => null,
            'checkout_to_type' => null,
            'redirect_option' => 'index',
            'table' => 'Users',
            'route' => route('users.index'),
        ],

        'Option item: redirect back to license ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => 999,
            'checkout_to_type' => null,
            'redirect_option' => 'item',
            'table' => 'Licenses',
            'route' => route('licenses.show', 999),
        ],

        'Option index: redirect back to license index ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => null,
            'checkout_to_type' => null,
            'redirect_option' => 'index',
            'table' => 'Licenses',
            'route' => route('licenses.index'),
        ],

        'Option item: redirect back to accessory list ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => 999,
            'checkout_to_type' => null,
            'redirect_option' => 'item',
            'table' => 'Accessories',
            'route' => route('accessories.show', 999),
        ],

        'Option index: redirect back to accessory index ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => null,
            'checkout_to_type' => null,
            'redirect_option' => 'index',
            'table' => 'Accessories',
            'route' => route('accessories.index'),
        ],
        'Option item: redirect back to consumable ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => 999,
            'checkout_to_type' => null,
            'redirect_option' => 'item',
            'table' => 'Consumables',
            'route' => route('consumables.show', 999),
        ],

        'Option index: redirect back to consumables index ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => null,
            'checkout_to_type' => null,
            'redirect_option' => 'index',
            'table' => 'Consumables',
            'route' => route('consumables.index'),
        ],

        'Option item: redirect back to component ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => 999,
            'checkout_to_type' => null,
            'redirect_option' => 'item',
            'table' => 'Components',
            'route' => route('components.show', 999),
        ],

        'Option index: redirect back to component index ' => [
            'request' => (object) ['assigned_asset' => null],
            'id' => null,
            'checkout_to_type' => null,
            'redirect_option' => 'index',
            'table' => 'Components',
            'route' => route('components.index'),
        ],
    ];

    foreach ($test_data as $scenario => $data) {

        Session::put('redirect_option', $data['redirect_option']);
        Session::put('checkout_to_type', $data['checkout_to_type']);

        $redirect = Helper::getRedirectOption($data['request'], $data['id'], $data['table']);


        expect($redirect)->toBeInstanceOf(RedirectResponse::class);
        expect($redirect->getTargetUrl())->toEqual($data['route'], $scenario . 'failed.');
    }
});
