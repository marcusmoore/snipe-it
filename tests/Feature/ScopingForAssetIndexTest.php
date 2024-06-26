<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Tests\Support\Provider;
use Tests\TestCase;

class ScopingForAssetIndexTest extends TestCase
{
    protected static function scenarios()
    {
        yield 'Super user should see assets from all companies' => Provider::data(function () {
            // @todo: attempt to clear this duplication
            [$companyA, $companyB] = Company::factory()->count(2)->create();

            $assetWithNoCompany = Asset::factory()->create();
            $assetForCompanyA = Asset::factory()->for($companyA)->create();
            $assetForCompanyB = Asset::factory()->for($companyB)->create();

            return [
                'actor' => User::factory()->superuser()->create(),
                'assertions' => fn() => $this->assertResponseContainsInRows($assetWithNoCompany, 'asset_tag', 'Asset with no company not included')
                    ->assertResponseContainsInRows($assetForCompanyA, 'asset_tag', 'Asset for Company A not included')
                    ->assertResponseContainsInRows($assetForCompanyB, 'asset_tag', 'Asset for Company B not included')

            ];
        });

        yield 'User in company should not see assets without company or from different company' => Provider::data(function () {
            [$companyA, $companyB] = Company::factory()->count(2)->create();

            $assetWithNoCompany = Asset::factory()->create();
            $assetForCompanyA = Asset::factory()->for($companyA)->create();
            $assetForCompanyB = Asset::factory()->for($companyB)->create();

            return [
                'actor' => User::factory()->for($companyA)->viewAssets()->create(),
                'assertions' => fn() => $this->assertResponseDoesNotContainInRows($assetWithNoCompany, 'asset_tag', 'Asset with no company included')
                    ->assertResponseContainsInRows($assetForCompanyA, 'asset_tag', 'Asset for Company A not included')
                    ->assertResponseDoesNotContainInRows($assetForCompanyB, 'asset_tag', 'Asset for Company B included')

            ];
        });

        yield 'User with no company should not see assets belonging to company' => Provider::data(function () {
            [$companyA, $companyB] = Company::factory()->count(2)->create();

            $assetWithNoCompany = Asset::factory()->create();
            $assetForCompanyA = Asset::factory()->for($companyA)->create();
            $assetForCompanyB = Asset::factory()->for($companyB)->create();

            return [
                'actor' => User::factory()->viewAssets()->create(['company_id' => null]),
                'assertions' => fn() => $this->assertResponseContainsInRows($assetWithNoCompany, 'asset_tag', 'Asset with no company not included')
                    ->assertResponseDoesNotContainInRows($assetForCompanyA, 'asset_tag', 'Asset for Company A not included')
                    ->assertResponseDoesNotContainInRows($assetForCompanyB, 'asset_tag', 'Asset for Company B not included')

            ];
        });
    }

    /** @dataProvider scenarios */
    public function testAssetIndexCompanyScoping($data)
    {
        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($data()['actor'])
            ->getJson(route('api.assets.index'))
            ->assertOk()
            ->checkAssertionsFromProvider($data);
    }
}
