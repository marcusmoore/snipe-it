<?php

namespace Tests\Feature\Assets\Api;

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetIndexTest extends TestCase
{
    public function testAssetApiIndexReturnsExpectedAssets()
    {
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
    }

    public function testAssetApiIndexReturnsDisplayUpcomingAuditsDue()
    {
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
    }

    public function testAssetApiIndexReturnsOverdueForAudit()
    {
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
    }


    public function testAssetApiIndexReturnsDueOrOverdueForAudit()
    {
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
    }



    public function testAssetApiIndexReturnsDueForExpectedCheckin()
    {
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
    }

    public function testAssetApiIndexReturnsOverdueForExpectedCheckin()
    {
        Asset::factory()->count(3)->create(['assigned_to' => '1', 'assigned_type' => User::class, 'expected_checkin' => Carbon::now()->subDays(1)->format('Y-m-d')]);
        
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.assets.list-upcoming', ['action' => 'checkins', 'upcoming_status' => 'overdue']))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
    }

    public function testAssetApiIndexReturnsDueOrOverdueForExpectedCheckin()
    {
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
    }

    public static function filterValues()
    {
        return [
            ['filter%5Bassigned_to%5D'],
            ['filter[assigned_to][not]=null'],
            ['filter={%22assigned_to%22:{%22$ne%22:null}}']
        ];
    }

    /**
     * [RB-17904]
     * [RB-19910]
     */
    #[DataProvider('filterValues')]
    public function test_handles_non_string_filter($filterString)
    {
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.assets.index') . '?' . $filterString)
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertMessagesContains('filter');
    }

    public function test_can_filter_results()
    {
        Asset::factory()->create(['purchase_date' => '2025-07-01', 'order_number' => '123']);
        Asset::factory()->create(['purchase_date' => '2025-07-01', 'order_number' => '123']);
        Asset::factory()->create(['purchase_date' => '2025-07-01', 'order_number' => '456']);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.assets.index', [
                'filter' => json_encode([
                    'order_number' => '123',
                    'purchase_date' => '2025-07-01',
                ]),
            ]))
            ->assertOk()
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 2)->etc());
    }

    public function testAssetApiIndexAdheresToCompanyScoping()
    {
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
    }
}
