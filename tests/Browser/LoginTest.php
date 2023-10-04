<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LoginTest extends DuskTestCase
{
    public function testCanSeeLoginPage()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(route('login'))
                ->assertSee(trans('auth/general.login_prompt'));
        });
    }

    public function testCanLogin()
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visitRoute('login')
                ->type('username', $user->username)
                ->type('password', 'password')
                ->press(trans('auth/general.login'))
                ->assertPathIs('/account/view-assets')
                ->screenshot('dashboard');
        });
    }
}
