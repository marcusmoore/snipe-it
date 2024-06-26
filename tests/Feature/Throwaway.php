<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Tests\Support\Provider;
use Tests\TestCase;

class Throwaway extends TestCase
{
    protected static function provider()
    {
        yield 'Admin for one company should be allowed to update user from same company' => Provider::data(function () {
            $company = Company::factory()->create();

            return [
                'admin' => User::factory()->for($company)->admin()->create(),
                'target' => User::factory()->for($company)->create(),
                'expected_status_code' => 200,
            ];
        });

        yield 'Admin for one company should NOT be allowed to update user from another company' => Provider::data(function () {
            [$companyA, $companyB] = Company::factory()->count(2)->create();

            return [
                'admin' => User::factory()->for($companyA)->admin()->create(),
                'target' => User::factory()->for($companyB)->create(),
                'expected_status_code' => 403,
            ];
        });

        yield 'Admin for one company should NOT be allowed to update user without a company' => Provider::data(function () {
            $company = Company::factory()->create();

            return [
                'admin' => User::factory()->for($company)->admin()->create(),
                'target' => User::factory()->create(['company_id' => null]),
                'expected_status_code' => 403,
            ];
        });

        yield 'Admin without a company should be allowed to update user without a company' => Provider::data(function () {
            return [
                'admin' => User::factory()->admin()->create(['company_id' => null]),
                'target' => User::factory()->create(['company_id' => null]),
                'expected_status_code' => 200,
            ];
        });

        yield 'Admin without a company should NOT be allowed to update user that has a company' => Provider::data(function () {
            return [
                'admin' => User::factory()->admin()->create(['company_id' => null]),
                'target' => User::factory()->for(Company::factory())->create(),
                'expected_status_code' => 403,
                // @todo:
                'assertions' => function () {
                    // $this->assert...
                }
            ];
        });
    }

    /** @dataProvider provider */
    public function testTheThing($data)
    {
        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($data()['admin'])
            // @todo: attempt to update name or another field...
            ->patchJson(route('api.users.update', $data()['target']))
            ->assertStatus($data()['expected_status_code']);
            // @todo: assert user was OR was not changed. Maybe tap($data()['assertions']())
    }

    // @todo: do the same for assets (or something else) and find the pattern...
}
