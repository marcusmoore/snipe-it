<?php

use App\Models\Company;
use App\Models\License;
use App\Models\User;

test('requires permission', function () {
    $license = License::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->deleteJson(route('api.licenses.destroy', $license))
        ->assertForbidden();

    $this->assertNotSoftDeleted($license);
});

test('adheres to full multiple companies support scoping', function () {
    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $licenseA = License::factory()->for($companyA)->create();
    $licenseB = License::factory()->for($companyB)->create();
    $licenseC = License::factory()->for($companyB)->create();

    $superUser = $companyA->users()->save(User::factory()->superuser()->make());
    $userInCompanyA = $companyA->users()->save(User::factory()->deleteLicenses()->make());
    $userInCompanyB = $companyB->users()->save(User::factory()->deleteLicenses()->make());

    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAsForApi($userInCompanyA)
        ->deleteJson(route('api.licenses.destroy', $licenseB))
        ->assertStatusMessageIs('error');

    $this->actingAsForApi($userInCompanyB)
        ->deleteJson(route('api.licenses.destroy', $licenseA))
        ->assertStatusMessageIs('error');

    $this->actingAsForApi($superUser)
        ->deleteJson(route('api.licenses.destroy', $licenseC))
        ->assertStatusMessageIs('success');

    $this->assertNotSoftDeleted($licenseA);
    $this->assertNotSoftDeleted($licenseB);
    $this->assertSoftDeleted($licenseC);
});

test('license cannot be deleted if still assigned', function () {
    $license = License::factory()->create(['seats' => 2]);
    $license->freeSeat()->update(['assigned_to' => User::factory()->create()->id]);

    $this->actingAsForApi(User::factory()->deleteLicenses()->create())
        ->deleteJson(route('api.licenses.destroy', $license))
        ->assertStatusMessageIs('error');

    $this->assertNotSoftDeleted($license);
});

test('can delete license', function () {
    $license = License::factory()->create();

    $this->actingAsForApi(User::factory()->deleteLicenses()->create())
        ->deleteJson(route('api.licenses.destroy', $license))
        ->assertStatusMessageIs('success');

    $this->assertSoftDeleted($license);
});

test('license seats are deleted when license is deleted', function () {
    $license = License::factory()->create(['seats' => 2]);

    expect($license->fresh()->licenseseats->isNotEmpty())->toBeTrue('License seats not created like expected');

    $this->actingAsForApi(User::factory()->deleteLicenses()->create())
        ->deleteJson(route('api.licenses.destroy', $license));

    expect($license->fresh()->licenseseats->isEmpty())->toBeTrue();
});
