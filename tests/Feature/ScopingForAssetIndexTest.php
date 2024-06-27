<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Tests\Support\Provider;
use Tests\TestCase;

class ScopingForAssetIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Provider::setUp(function () {
            [$companyA, $companyB] = Company::factory()->count(2)->create();

            $assetWithNoCompany = Asset::factory()->create();
            $assetForCompanyA = Asset::factory()->for($companyA)->create();
            $assetForCompanyB = Asset::factory()->for($companyB)->create();

            return [
                'company_a' => $companyA,
                'company_b' => $companyB,
                'asset_with_no_company' => $assetWithNoCompany,
                'asset_for_company_a' => $assetForCompanyA,
                'asset_for_company_b' => $assetForCompanyB
            ];
        });
    }

    protected static function scenarios()
    {
        yield 'Super user should see assets from all companies' => Provider::data(function () {
            return [
                'actor' => User::factory()->superuser()->create(),
                'assertions' => function () {
                    return $this->assertResponseContainsInRows($this->provider('asset_with_no_company'), 'asset_tag')
                        ->assertResponseContainsInRows($this->provider('asset_for_company_a'), 'asset_tag')
                        ->assertResponseContainsInRows($this->provider('asset_for_company_b'), 'asset_tag');
                }
            ];
        });

        yield 'User in company should not see assets without company or from different company' => Provider::data(function () {
            return [
                'actor' => User::factory()->for($this->provider('company_a'))->viewAssets()->create(),
                'assertions' => function () {
                    return $this->assertResponseDoesNotContainInRows(
                        $this->provider('asset_with_no_company'),
                        'asset_tag'
                    )->assertResponseContainsInRows(
                        $this->provider('asset_for_company_a'),
                        'asset_tag'
                    )->assertResponseDoesNotContainInRows(
                        $this->provider('asset_for_company_b'),
                        'asset_tag'
                    );
                }
            ];
        });

        yield 'User with no company should not see assets belonging to company' => Provider::data(function () {
            return [
                'actor' => User::factory()->viewAssets()->create(['company_id' => null]),
                'assertions' => function () {
                    return $this->assertResponseContainsInRows(
                        Provider::get('asset_with_no_company'),
                        'asset_tag'
                    )->assertResponseDoesNotContainInRows(
                        Provider::get('asset_for_company_a'),
                        'asset_tag'
                    )->assertResponseDoesNotContainInRows(
                        Provider::get('asset_for_company_b'),
                        'asset_tag'
                    );
                }
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
