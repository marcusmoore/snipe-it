<?php

namespace Tests\Feature\CheckoutAcceptances;

use App\Models\CheckoutAcceptance;
use App\Models\Setting;
use App\Models\User;
use Tests\Support\InteractsWithSettings;
use Tests\TestCase;

class UnauthenticatedAcceptanceTest extends TestCase
{
    use InteractsWithSettings;

    protected function setUp(): void
    {
        parent::setUp();

        // this is only here to get past settings setup
        User::factory()->create();
    }

    public function testUnknownAcceptanceReturns404()
    {
        $this->get(route('unauthenticated-acceptance.show', ['acceptance' => 'bad-uuid']))
            ->assertNotFound();
    }

    public function testAttemptingToLoadAcceptanceAlreadyAcceptedOrDeniedReturns404()
    {
        $checkoutAcceptance = CheckoutAcceptance::factory()
            ->forAsset()
            ->allowsUnauthorizedAcceptance()
            ->accepted()
            ->create();

        $this->get(route('unauthenticated-acceptance.show', ['acceptance' => $checkoutAcceptance->uuid]))
            ->assertNotFound();
    }

    public function testCanLoadAcceptanceByUuid()
    {
        $checkoutAcceptance = CheckoutAcceptance::factory()
            ->forAsset()
            ->allowsUnauthorizedAcceptance()
            ->create();

        $this->get(route('unauthenticated-acceptance.show', ['acceptance' => $checkoutAcceptance->uuid]))
            ->assertOk()
            ->assertSee($checkoutAcceptance->checkoutable->present()->name());
    }

    public function testCanAcceptAcceptance()
    {
        $this->markTestIncomplete();

        // submit form
    }

    public function testCanDeclineAcceptance()
    {
        $this->markTestIncomplete();

        // submit form
    }
}
