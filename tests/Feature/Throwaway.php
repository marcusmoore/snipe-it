<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class Throwaway extends TestCase
{
    protected static function provider()
    {
        // yield 'Admin updating user in same company' => [
        //     function () {
        //         return once(function () {
        //             $company = Company::factory()->create();
        //
        //             $bag = new BagOfHolding;
        //             $bag->setActor(User::factory()->for($company)->admin()->create());
        //             $bag->setSubject(User::factory()->for($company)->create());
        //             $bag->setStatusCode(200);
        //
        //             return $bag;
        //         });
        //     }
        // ];

        // yield 'Admin attempting to update user in another company' => [
        //     function () {
        //         return once(function () {
        //             [$companyA, $companyB] = Company::factory()->count(2)->create();
        //
        //             $bag = new BagOfHolding;
        //
        //             $bag->setActor(User::factory()->for($companyA)->admin()->create());
        //             $bag->setSubject(User::factory()->for($companyB)->create());
        //             $bag->setStatusCode(403);
        //
        //             return $bag;
        //         });
        //     }
        // ];

        yield 'Admin attempting to update user without a company' => Whelp::hereWeGo(function () {
            [$companyA, $companyB] = Company::factory()->count(2)->create();

            $bag = new BagOfHolding;

            $bag->setActor(User::factory()->for($companyA)->admin()->create());
            $bag->setSubject(User::factory()->for($companyB)->create());
            $bag->setStatusCode(403);

            return $bag;
        });
    }

    /** @dataProvider provider */
    public function testTheThing($bag)
    {
        $this->settings->enableMultipleFullCompanySupport();
        // dd($bag);

        // @todo: nope...
        // $this->assertEquals($bag()->actor->address, $bag()->actor->address);
        // $this->assertEquals($bag->actor->address, $bag->actor->address);

        $this->actingAsForApi($bag()->actor)
            ->patchJson(route('api.users.update', $bag()->subject))
            ->assertStatus($bag()->statusCode);
    }
}
