<?php

use App\Models\Accessory;
use App\Models\Company;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\TestsFullMultipleCompaniesSupport;
use Tests\Concerns\TestsPermissionsRequirement;

test('requires permission', function () {
    $accessory = Accessory::factory()->create();

    $this->actingAsForApi(User::factory()->create())
        ->deleteJson(route('api.accessories.destroy', $accessory))
        ->assertForbidden();

    $this->assertNotSoftDeleted($accessory);
});

test('adheres to full multiple companies support scoping', function () {
    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $accessoryA = Accessory::factory()->for($companyA)->create();
    $accessoryB = Accessory::factory()->for($companyB)->create();
    $accessoryC = Accessory::factory()->for($companyB)->create();

    $superUser = $companyA->users()->save(User::factory()->superuser()->make());
    $userInCompanyA = $companyA->users()->save(User::factory()->deleteAccessories()->make());
    $userInCompanyB = $companyB->users()->save(User::factory()->deleteAccessories()->make());

    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAsForApi($userInCompanyA)
        ->deleteJson(route('api.accessories.destroy', $accessoryB))
        ->assertStatusMessageIs('error');

    $this->actingAsForApi($userInCompanyB)
        ->deleteJson(route('api.accessories.destroy', $accessoryA))
        ->assertStatusMessageIs('error');

    $this->actingAsForApi($superUser)
        ->deleteJson(route('api.accessories.destroy', $accessoryC))
        ->assertStatusMessageIs('success');

    $this->assertNotSoftDeleted($accessoryA);
    $this->assertNotSoftDeleted($accessoryB);
    $this->assertSoftDeleted($accessoryC);
});

dataset('checkedOutAccessories', function () {
    yield 'checked out to user' => [fn() => Accessory::factory()->checkedOutToUser()->create()];
    yield 'checked out to asset' => [fn() => Accessory::factory()->checkedOutToAsset()->create()];
    yield 'checked out to location' => [fn() => Accessory::factory()->checkedOutToLocation()->create()];
});

test('cannot delete accessory that has checkouts', function ($data) {
    $accessory = $data();

    $this->actingAsForApi(User::factory()->deleteAccessories()->create())
        ->deleteJson(route('api.accessories.destroy', $accessory))
        ->assertStatusMessageIs('error');

    $this->assertNotSoftDeleted($accessory);
})->with('checkedOutAccessories');

test('can delete accessory', function () {
    $accessory = Accessory::factory()->create();

    $this->actingAsForApi(User::factory()->deleteAccessories()->create())
        ->deleteJson(route('api.accessories.destroy', $accessory))
        ->assertStatusMessageIs('success');

    $this->assertSoftDeleted($accessory);
});
