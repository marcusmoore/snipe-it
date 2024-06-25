<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class Throwaway extends TestCase
{
    protected static function provider()
    {
        yield 'Admin attempting to update user without a company' => Provider::data(function () {
            [$companyA, $companyB] = Company::factory()->count(2)->create();

            return [
                'actor' => User::factory()->for($companyA)->admin()->create(),
                'subject' => User::factory()->for($companyB)->create(),
                'status_code' => 403,
            ];
        });
    }

    /** @dataProvider provider */
    public function testTheThing($bag)
    {
        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($bag()['actor'])
            ->patchJson(route('api.users.update', $bag()['subject']))
            ->assertStatus($bag()['status_code']);
    }
}
