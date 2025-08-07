<?php

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;

pest()->group('assets', 'api');

test('asset api index returns expected assets', function () {
    Asset::factory()->count(3)->create();

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(
            route('api.assets.index', [
                'sort' => 'name',
                'order' => 'asc',
                'offset' => '0',
                'limit' => '20',
            ]))
        ->assertOk()
        ->assertJsonStructure([
            'total',
            'rows',
        ])
        ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
});

test('asset api index returns display upcoming audits due', function () {
    Asset::factory()->count(3)->create(['next_audit_date' => Carbon::now()->format('Y-m-d')]);

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(
            route('api.assets.list-upcoming', ['action' => 'audits', 'upcoming_status' => 'due']))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
        ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
});

test('asset api index returns overdue for audit', function () {
    Asset::factory()->count(3)->create(['next_audit_date' => Carbon::now()->subDays(1)->format('Y-m-d')]);

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(
            route('api.assets.list-upcoming', ['action' => 'audits', 'upcoming_status' => 'overdue']))
        ->assertOk()
        ->assertJsonStructure([
            'total',
            'rows',
        ])
        ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
});

test('asset api index returns due or overdue for audit', function () {
    Asset::factory()->count(3)->create(['next_audit_date' => Carbon::now()->format('Y-m-d')]);
    Asset::factory()->count(2)->create(['next_audit_date' => Carbon::now()->subDays(1)->format('Y-m-d')]);

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(
            route('api.assets.list-upcoming', ['action' => 'audits', 'upcoming_status' => 'due-or-overdue']))
        ->assertOk()
        ->assertJsonStructure([
            'total',
            'rows',
        ])
        ->assertJson(fn(AssertableJson $json) => $json->has('rows', 5)->etc());
});

test('asset api index returns due for expected checkin', function () {
    Asset::factory()->count(3)->create(['assigned_to' => '1', 'assigned_type' => User::class, 'expected_checkin' => Carbon::now()->format('Y-m-d')]);

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(
            route('api.assets.list-upcoming', ['action' => 'checkins', 'upcoming_status' => 'due'])
        )
        ->assertOk()
        ->assertJsonStructure([
            'total',
            'rows',
    ])
    ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
});

test('asset api index returns overdue for expected checkin', function () {
    Asset::factory()->count(3)->create(['assigned_to' => '1', 'assigned_type' => User::class, 'expected_checkin' => Carbon::now()->subDays(1)->format('Y-m-d')]);

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(route('api.assets.list-upcoming', ['action' => 'checkins', 'upcoming_status' => 'overdue']))
        ->assertOk()
        ->assertJsonStructure([
            'total',
            'rows',
        ])
        ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
});

test('asset api index returns due or overdue for expected checkin', function () {
    Asset::factory()->count(3)->create(['assigned_to' => '1', 'assigned_type' => User::class, 'expected_checkin' => Carbon::now()->subDays(1)->format('Y-m-d')]);
    Asset::factory()->count(2)->create(['assigned_to' => '1', 'assigned_type' => User::class, 'expected_checkin' => Carbon::now()->format('Y-m-d')]);

    $this->actingAsForApi(User::factory()->superuser()->create())
        ->getJson(route('api.assets.list-upcoming', ['action' => 'checkins', 'upcoming_status' => 'due-or-overdue']))
        ->assertOk()
        ->assertJsonStructure([
            'total',
            'rows',
        ])
        ->assertJson(fn(AssertableJson $json) => $json->has('rows', 5)->etc());
});

test('asset api index adheres to company scoping', function () {
    [$companyA, $companyB] = Company::factory()->count(2)->create();

    $assetA = Asset::factory()->for($companyA)->create();
    $assetB = Asset::factory()->for($companyB)->create();

    $superUser = $companyA->users()->save(User::factory()->superuser()->make());
    $userInCompanyA = $companyA->users()->save(User::factory()->viewAssets()->make());
    $userInCompanyB = $companyB->users()->save(User::factory()->viewAssets()->make());

    $this->settings->disableMultipleFullCompanySupport();

    $this->actingAsForApi($superUser)
        ->getJson(route('api.assets.index'))
        ->assertResponseContainsInRows($assetA, 'asset_tag')
        ->assertResponseContainsInRows($assetB, 'asset_tag');

    $this->actingAsForApi($userInCompanyA)
        ->getJson(route('api.assets.index'))
        ->assertResponseContainsInRows($assetA, 'asset_tag')
        ->assertResponseContainsInRows($assetB, 'asset_tag');

    $this->actingAsForApi($userInCompanyB)
        ->getJson(route('api.assets.index'))
        ->assertResponseContainsInRows($assetA, 'asset_tag')
        ->assertResponseContainsInRows($assetB, 'asset_tag');

    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAsForApi($superUser)
        ->getJson(route('api.assets.index'))
        ->assertResponseContainsInRows($assetA, 'asset_tag')
        ->assertResponseContainsInRows($assetB, 'asset_tag');

    $this->actingAsForApi($userInCompanyA)
        ->getJson(route('api.assets.index'))
        ->assertResponseContainsInRows($assetA, 'asset_tag')
        ->assertResponseDoesNotContainInRows($assetB, 'asset_tag');

    $this->actingAsForApi($userInCompanyB)
        ->getJson(route('api.assets.index'))
        ->assertResponseDoesNotContainInRows($assetA, 'asset_tag')
        ->assertResponseContainsInRows($assetB, 'asset_tag');
});
