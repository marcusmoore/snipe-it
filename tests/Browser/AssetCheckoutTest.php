<?php

namespace Tests\Browser;

use App\Models\Asset;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Support\InteractsWithAuthentication;

class AssetCheckoutTest extends DuskTestCase
{
    use InteractsWithAuthentication;

    public function testCanCheckoutAssetToUser(): void
    {
        $this->markTestIncomplete();

        $admin = User::factory()->superuser()->create();
        $user = User::factory()->superuser()->create();
        $asset = Asset::factory()->create();

        // @todo remove this: set up to see if "Assets currently checked out to this user" populates. it does not.
        Asset::factory()->create()->checkOut($user, $admin);

        $this->browse(function (Browser $browser) use ($user, $asset, $admin) {
            $browser->loginAs($admin)
                ->visit(route('hardware.checkout.create', $asset->id))
                ->assertSee(trans('admin/hardware/general.checkout'))
                ->type('name', 'Changed Asset Name')
                ->click('@checkout-selector-user')
                ->tap(function ($browser) use ($user) {
                    // @todo: this doesn't work since select2 sends request to api after selection
                    $browser->script([
                        "$('#assigned_user_select').val('{$user->id}')",
                        "$('#assigned_user_select').trigger('change')",
                    ]);
                })
                // ->click('@asset-checkout-submit-button')
                ->stop();
        });
    }
}
