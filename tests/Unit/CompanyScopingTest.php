<?php

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\Company;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

test('company scoping', function ($model) {
    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $modelA = $model::factory()->for($companyA)->create();
    $modelB = $model::factory()->for($companyB)->create();

    $superUser = $companyA->users()->save(User::factory()->superuser()->make());
    $userInCompanyA = $companyA->users()->save(User::factory()->make());
    $userInCompanyB = $companyB->users()->save(User::factory()->make());

    $this->settings->disableMultipleFullCompanySupport();

    $this->actingAs($superUser);
    assertCanSee($modelA);
    assertCanSee($modelB);

    $this->actingAs($userInCompanyA);
    assertCanSee($modelA);
    assertCanSee($modelB);

    $this->actingAs($userInCompanyB);
    assertCanSee($modelA);
    assertCanSee($modelB);

    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAs($superUser);
    assertCanSee($modelA);
    assertCanSee($modelB);

    $this->actingAs($userInCompanyA);
    assertCanSee($modelA);
    assertCannotSee($modelB);

    $this->actingAs($userInCompanyB);
    assertCannotSee($modelA);
    assertCanSee($modelB);
})->with([
    'Accessories' => [Accessory::class],
    'Assets' => [Asset::class],
    'Components' => [Component::class],
    'Consumables' => [Consumable::class],
    'Licenses' => [License::class],
]);

test('asset maintenance company scoping', function () {
    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $assetMaintenanceForCompanyA = AssetMaintenance::factory()->for(Asset::factory()->for($companyA))->create();
    $assetMaintenanceForCompanyB = AssetMaintenance::factory()->for(Asset::factory()->for($companyB))->create();

    $superUser = $companyA->users()->save(User::factory()->superuser()->make());
    $userInCompanyA = $companyA->users()->save(User::factory()->make());
    $userInCompanyB = $companyB->users()->save(User::factory()->make());

    $this->settings->disableMultipleFullCompanySupport();

    $this->actingAs($superUser);
    assertCanSee($assetMaintenanceForCompanyA);
    assertCanSee($assetMaintenanceForCompanyB);

    $this->actingAs($userInCompanyA);
    assertCanSee($assetMaintenanceForCompanyA);
    assertCanSee($assetMaintenanceForCompanyB);

    $this->actingAs($userInCompanyB);
    assertCanSee($assetMaintenanceForCompanyA);
    assertCanSee($assetMaintenanceForCompanyB);

    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAs($superUser);
    assertCanSee($assetMaintenanceForCompanyA);
    assertCanSee($assetMaintenanceForCompanyB);

    $this->actingAs($userInCompanyA);
    assertCanSee($assetMaintenanceForCompanyA);
    assertCannotSee($assetMaintenanceForCompanyB);

    $this->actingAs($userInCompanyB);
    assertCannotSee($assetMaintenanceForCompanyA);
    assertCanSee($assetMaintenanceForCompanyB);
});

test('license seat company scoping', function () {
    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $licenseSeatA = LicenseSeat::factory()->for(Asset::factory()->for($companyA))->create();
    $licenseSeatB = LicenseSeat::factory()->for(Asset::factory()->for($companyB))->create();

    $superUser = $companyA->users()->save(User::factory()->superuser()->make());
    $userInCompanyA = $companyA->users()->save(User::factory()->make());
    $userInCompanyB = $companyB->users()->save(User::factory()->make());

    $this->settings->disableMultipleFullCompanySupport();

    $this->actingAs($superUser);
    assertCanSee($licenseSeatA);
    assertCanSee($licenseSeatB);

    $this->actingAs($userInCompanyA);
    assertCanSee($licenseSeatA);
    assertCanSee($licenseSeatB);

    $this->actingAs($userInCompanyB);
    assertCanSee($licenseSeatA);
    assertCanSee($licenseSeatB);

    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAs($superUser);
    assertCanSee($licenseSeatA);
    assertCanSee($licenseSeatB);

    $this->actingAs($userInCompanyA);
    assertCanSee($licenseSeatA);
    assertCannotSee($licenseSeatB);

    $this->actingAs($userInCompanyB);
    assertCannotSee($licenseSeatA);
    assertCanSee($licenseSeatB);
});

function assertCanSee(Model $model)
{
    expect(get_class($model)::all()->contains($model))->toBeTrue('User was not able to see expected model');
}

function assertCannotSee(Model $model)
{
    expect(get_class($model)::all()->contains($model))->toBeFalse('User was able to see model from a different company');
}
