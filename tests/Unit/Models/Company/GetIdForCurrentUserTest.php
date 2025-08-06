<?php

use App\Models\Company;
use App\Models\User;

test('returns provided value when full company support disabled', function () {
    $this->settings->disableMultipleFullCompanySupport();

    $this->actingAs(User::factory()->create());
    expect(Company::getIdForCurrentUser(1000))->toEqual(1000);
});

test('returns provided value for super users when full company support enabled', function () {
    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAs(User::factory()->superuser()->create());
    expect(Company::getIdForCurrentUser(2000))->toEqual(2000);
});

test('returns non super users company id when full company support enabled', function () {
    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAs(User::factory()->forCompany(['id' => 2000])->create());
    expect(Company::getIdForCurrentUser(1000))->toEqual(2000);
});

test('returns null for non super user without company id when full company support enabled', function () {
    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAs(User::factory()->create(['company_id' => null]));
    expect(Company::getIdForCurrentUser(1000))->toBeNull();
});
