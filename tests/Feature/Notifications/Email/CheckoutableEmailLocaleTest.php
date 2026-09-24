<?php

namespace Tests\Feature\Notifications\Email;

use App\Events\CheckoutableCheckedIn;
use App\Events\CheckoutableCheckedOut;
use App\Mail\CheckinAssetMail;
use App\Mail\CheckoutAssetMail;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\User;
use Closure;
use Generator;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('notifications')]
class CheckoutableEmailLocaleTest extends TestCase
{
    private const SETTINGS_LOCALE = 'pt-PT';

    private const ACTING_ADMIN_LOCALE = 'de-DE';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->settings->set(['locale' => self::SETTINGS_LOCALE]);
    }

    public static function checkoutAndCheckin(): Generator
    {
        yield 'checkout' => [
            CheckoutAssetMail::class,
            fn (Asset $asset, User $user) => event(new CheckoutableCheckedOut(
                $asset,
                $user,
                User::factory()->superuser()->create(),
                '',
            )),
        ];

        yield 'checkin' => [
            CheckinAssetMail::class,
            fn (Asset $asset, User $user) => event(new CheckoutableCheckedIn(
                $asset,
                $user,
                User::factory()->superuser()->create(),
                '',
            )),
        ];
    }

    #[Test]
    #[DataProvider('checkoutAndCheckin')]
    public function user_email_is_sent_in_the_users_locale(string $mailable, Closure $fireEvent): void
    {
        $user = User::factory()->create(['locale' => 'es-ES']);
        $asset = $this->assetThatEmailsTheUser();
        app()->setLocale(self::ACTING_ADMIN_LOCALE);

        $fireEvent($asset, $user);

        $this->assertSame('es-ES', $this->sentTo($mailable, $user->email)->locale);
    }

    #[Test]
    #[DataProvider('checkoutAndCheckin')]
    public function user_and_cc_emails_are_sent_separately_in_their_own_locales(string $mailable, Closure $fireEvent): void
    {
        $this->settings->enableAdminCC('cc@example.com')->enableAdminCCAlways();
        $user = User::factory()->create(['locale' => 'es-ES']);
        $asset = $this->assetThatEmailsTheUser();
        app()->setLocale(self::ACTING_ADMIN_LOCALE);

        $fireEvent($asset, $user);

        Mail::assertSentCount(2);

        $userMail = $this->sentTo($mailable, $user->email);
        $this->assertSame('es-ES', $userMail->locale);
        $this->assertFalse($userMail->hasCc('cc@example.com'));

        $ccMail = Mail::sent($mailable, fn ($mail) => $mail->hasCc('cc@example.com'))->sole();
        $this->assertSame(self::SETTINGS_LOCALE, $ccMail->locale);
        $this->assertFalse($ccMail->hasTo($user->email));
    }

    #[Test]
    #[DataProvider('checkoutAndCheckin')]
    public function admin_only_email_is_sent_in_the_settings_locale_when_the_user_has_no_email(string $mailable, Closure $fireEvent): void
    {
        $this->settings->enableAdminCC('cc@example.com')->enableAdminCCAlways();
        $user = User::factory()->create(['email' => null, 'locale' => 'es-ES']);
        $asset = $this->assetThatEmailsTheUser();
        app()->setLocale(self::ACTING_ADMIN_LOCALE);

        $fireEvent($asset, $user);

        $this->assertSame(self::SETTINGS_LOCALE, $this->sentTo($mailable, 'cc@example.com')->locale);
    }

    #[Test]
    #[DataProvider('checkoutAndCheckin')]
    public function user_email_falls_back_to_the_settings_locale_when_the_user_has_no_locale(string $mailable, Closure $fireEvent): void
    {
        $user = User::factory()->create(['locale' => null]);
        $asset = $this->assetThatEmailsTheUser();
        app()->setLocale(self::ACTING_ADMIN_LOCALE);

        $fireEvent($asset, $user);

        $this->assertSame(self::SETTINGS_LOCALE, $this->sentTo($mailable, $user->email)->locale);
    }

    #[Test]
    #[DataProvider('checkoutAndCheckin')]
    public function user_email_maps_a_legacy_locale_code_to_its_current_code(string $mailable, Closure $fireEvent): void
    {
        $user = User::factory()->create(['locale' => 'it']);
        $asset = $this->assetThatEmailsTheUser();
        app()->setLocale(self::ACTING_ADMIN_LOCALE);

        $fireEvent($asset, $user);

        $this->assertSame('it-IT', $this->sentTo($mailable, $user->email)->locale);
    }

    private function assetThatEmailsTheUser(): Asset
    {
        $category = Category::factory()->create([
            'checkin_email' => true,
            'eula_text' => null,
            'require_acceptance' => false,
            'use_default_eula' => false,
        ]);

        return Asset::factory()->for(AssetModel::factory()->for($category), 'model')->create();
    }

    private function sentTo(string $mailable, string $address): object
    {
        return Mail::sent($mailable, fn ($mail) => $mail->hasTo($address))->sole();
    }
}
