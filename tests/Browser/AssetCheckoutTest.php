<?php

namespace Tests\Browser;

use App\Models\Asset;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AssetCheckoutTest extends DuskTestCase
{
    public function testExample(): void
    {
        $this->markTestIncomplete();

        $user = User::factory()->checkoutAssets()->create();
        $asset = Asset::factory()->create();

        $this->browse(function (Browser $browser) use ($asset, $user) {
            $browser->loginAs($user)
                ->visit(route('hardware.checkout.create', $asset->id))
                ->assertSee(trans('admin/hardware/general.checkout'))
                ->type('name', 'Changed Asset Name')
                ->click('@checkout-selector-location')
                ->stop();
        });
    }
}
