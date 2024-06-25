<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Spatie\Once\Cache;
use Tests\Support\Provider;
use Tests\TestCase;

class Throwaway extends TestCase
{
    protected static function provider()
    {
        yield 'Admin for one company should be allowed to update user from same company' => Provider::data(function () {
            $company = Company::factory()->create();

            return [
                'actor' => User::factory()->for($company)->admin()->create(),
                'subject' => User::factory()->for($company)->create(),
                'status_code' => 200,
            ];
        });

        yield 'Admin for one company should NOT be allowed to update user from another company' => Provider::data(function () {
            [$companyA, $companyB] = Company::factory()->count(2)->create();

            return [
                // @todo:
                'actor' => User::factory()->for($companyA)->admin()->create(),
                'subject' => User::factory()->for($companyB)->create(),
                'status_code' => 403,
            ];
        });

        yield 'Admin for one company should NOT be allowed to update user without a company' => Provider::data(function () {
            $company = Company::factory()->create();

            return [
                'actor' => User::factory()->for($company)->admin()->create(),
                'subject' => User::factory()->create(['company_id' => null]),
                'status_code' => 403,
            ];
        });

        yield 'Admin without a company should be allowed to update user without a company' => Provider::data(function () {
            return [
                'actor' => User::factory()->admin()->create(['company_id' => null]),
                'subject' => User::factory()->create(['company_id' => null]),
                'status_code' => 200,
            ];
        });

        yield 'Admin without a company should NOT be allowed to update user that has a company' => Provider::data(function () {
            return [
                'actor' => User::factory()->admin()->create(['company_id' => null]),
                'subject' => User::factory()->for(Company::factory())->create(),
                'status_code' => 403,
            ];
        });
    }

    /** @dataProvider provider */
    public function testTheThing($data)
    {
        $this->beforeApplicationDestroyed(fn() => Cache::getInstance()->flush());

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($data()['actor'])
            ->patchJson(route('api.users.update', $data()['subject']))
            ->assertStatus($data()['status_code']);
    }
}
