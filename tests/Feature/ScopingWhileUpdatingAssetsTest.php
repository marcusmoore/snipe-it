<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Tests\Support\Provider;
use Tests\TestCase;

class ScopingWhileUpdatingAssetsTest extends TestCase
{
    protected static function scenarios()
    {
        yield 'Admin for one company should be allowed to update asset from same company' => Provider::data(function () {
            $company = Company::factory()->create();
            return [
                'admin' => User::factory()->for($company)->admin()->create(),
                'target' => Asset::factory()->for($company)->create(),
                'expected_status_code' => 200,
                'assertions' => function () {
                    $this->assertDatabaseHas('assets', [
                        'name' => 'Purple Lightsaber'
                    ]);
                }
            ];
        });

        yield 'Admin for one company should NOT be allowed to update asset from another company' => Provider::data(function () {
            [$companyA, $companyB] = Company::factory()->count(2)->create();

            return [
                'admin' => User::factory()->for($companyA)->admin()->create(),
                'target' => Asset::factory()->for($companyB)->create(),
                'expected_status_code' => 403,
                'assertions' => function () {
                    $this->assertDatabaseMissing('assets', [
                        'name' => 'Purple Lightsaber'
                    ]);
                }
            ];
        });

        yield 'Admin for one company should NOT be allowed to update asset without a company' => Provider::data(function () {
            $company = Company::factory()->create();

            return [
                'admin' => User::factory()->for($company)->admin()->create(),
                'target' => Asset::factory()->create(['company_id' => null]),
                'expected_status_code' => 403,
                'assertions' => function () {
                    $this->assertDatabaseMissing('assets', [
                        'name' => 'Purple Lightsaber'
                    ]);
                }
            ];
        });

        yield 'Admin without a company should be allowed to update asset without a company' => Provider::data(function () {
            return [
                'admin' => User::factory()->admin()->create(['company_id' => null]),
                'target' => Asset::factory()->create(['company_id' => null]),
                'expected_status_code' => 200,
                'assertions' => function () {
                    $this->assertDatabaseMissing('assets', [
                        'name' => 'Purple Lightsaber'
                    ]);
                }
            ];
        });

        yield 'Admin without a company should NOT be allowed to update asset that has a company' => Provider::data(function () {
            return [
                'admin' => User::factory()->admin()->create(['company_id' => null]),
                'target' => Asset::factory()->for(Company::factory())->create(),
                'expected_status_code' => 403,
                'assertions' => function () {
                    $this->assertDatabaseMissing('assets', [
                        'name' => 'Purple Lightsaber'
                    ]);
                }
            ];
        });
    }

    /** @dataProvider scenarios */
    public function testUpdatingAssetWithFullCompanySupport($data)
    {
        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($data()['admin'])
            ->patchJson(route('api.assets.update', $data()['target']), [
                'name' => 'Purple Lightsaber'
            ])
            ->assertStatus($data()['expected_status_code']);

        $this->checkAssertionsFromProvider($data());
    }
}
