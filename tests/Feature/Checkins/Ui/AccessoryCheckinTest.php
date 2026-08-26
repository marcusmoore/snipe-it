<?php

namespace Tests\Feature\Checkins\Ui;

use App\Events\CheckoutableCheckedIn;
use App\Mail\CheckinAccessoryMail;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CheckinAccessoryNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessoryCheckinTest extends TestCase
{
    #[Test]
    public function checking_in_accessory_requires_correct_permission()
    {
        $accessory = Accessory::factory()->checkedOutToUser()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('accessories.checkin.store', $accessory->checkouts->first()->id))
            ->assertForbidden();
    }

    #[Test]
    public function page_renders()
    {
        $accessory = Accessory::factory()->checkedOutToUser()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('accessories.checkin.show', $accessory->checkouts->first()->id))
            ->assertOk();
    }

    #[Test]
    public function accessory_can_be_checked_in()
    {
        Event::fake([CheckoutableCheckedIn::class]);

        $user = User::factory()->create();
        $accessory = Accessory::factory()->checkedOutToUser($user)->create();

        $this->assertTrue($accessory->checkouts()->where('assigned_type', User::class)->where('assigned_to', $user->id)->count() > 0);

        $this->actingAs(User::factory()->checkinAccessories()->create())
            ->post(route('accessories.checkin.store', $accessory->checkouts->first()->id));

        $this->assertFalse($accessory->fresh()->checkouts()->where('assigned_type', User::class)->where('assigned_to', $user->id)->count() > 0);

        Event::assertDispatched(CheckoutableCheckedIn::class, 1);
    }

    #[Test]
    public function email_sent_to_user_if_setting_enabled()
    {
        Mail::fake();

        $user = User::factory()->create();
        $accessory = Accessory::factory()->checkedOutToUser($user)->create();

        $accessory->category->update(['checkin_email' => true]);

        event(new CheckoutableCheckedIn(
            $accessory,
            $user,
            User::factory()->checkinAccessories()->create(),
            '',
        ));
        Mail::assertSent(CheckinAccessoryMail::class, function (CheckinAccessoryMail $mail) use ($user) {
            return $mail->hasTo($user->email);

        });
    }

    #[Test]
    public function email_not_sent_to_user_if_setting_disabled()
    {
        Mail::fake();

        $user = User::factory()->create();
        $accessory = Accessory::factory()->checkedOutToUser($user)->create();

        $accessory->category->update([
            'checkin_email' => false,
            'require_acceptance' => false,
            'eula_text' => null,
        ]);

        event(new CheckoutableCheckedIn(
            $accessory,
            $user,
            User::factory()->checkinAccessories()->create(),
            '',
        ));

        Mail::assertNotSent(CheckinAccessoryMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    #[Test]
    public function accessory_checkin_clears_pending_acceptance_when_notifications_are_fully_disabled()
    {
        $this->settings->disableAdminCC()->disableAdminCCAlways()->disableSlackWebhook();

        $user = User::factory()->create();
        $accessory = Accessory::factory()->checkedOutToUser($user)->create();

        $accessory->category->update([
            'require_acceptance' => true,
            'checkin_email' => false,
        ]);

        $acceptance = CheckoutAcceptance::factory()->forAccessory()->pending()->create([
            'checkoutable_id' => $accessory->id,
            'assigned_to_id' => $user->id,
        ]);

        $this->assertTrue($acceptance->isPending());
        $this->assertNoNotificationChannelIsConfigured($accessory);

        $this->checkInAccessoryFrom($accessory, $user);

        $this->assertAcceptanceWasSoftDeleted(
            $acceptance,
            'The pending acceptance should have been soft-deleted when the accessory was checked in but it survived.'
        );
    }

    #[Test]
    public function accessory_checkin_clears_pending_acceptance_when_only_a_webhook_is_configured()
    {
        Notification::fake();

        $this->settings->disableAdminCC()->disableAdminCCAlways()->enableSlackWebhook();

        $user = User::factory()->create();
        $accessory = Accessory::factory()->checkedOutToUser($user)->create();

        $accessory->category->update([
            'require_acceptance' => true,
            'checkin_email' => false,
        ]);

        $acceptance = CheckoutAcceptance::factory()->forAccessory()->pending()->create([
            'checkoutable_id' => $accessory->id,
            'assigned_to_id' => $user->id,
        ]);

        $this->assertTrue($acceptance->isPending());

        $this->checkInAccessoryFrom($accessory, $user);

        $this->assertSlackNotificationSent(CheckinAccessoryNotification::class);

        $this->assertAcceptanceWasSoftDeleted(
            $acceptance,
            'The pending acceptance should have been soft-deleted when the accessory was checked in but it survived.'
        );
    }

    #[Test]
    public function accessory_checkin_does_not_clear_a_same_id_pending_acceptance_of_another_type()
    {
        Mail::fake();

        $this->settings->disableAdminCC()->disableAdminCCAlways()->disableSlackWebhook();

        $user = User::factory()->create();

        [$accessory, $asset] = $this->createAccessoryAndAssetSharingAnId($user);

        // Notifications on for this one. With checkin_email off the delete never
        // runs at all and this would be a re-run of the two tests above rather
        // than a test of the missing morph filter.
        $accessory->category->update(['checkin_email' => true]);
        $this->assertTrue((bool) $accessory->fresh()->checkin_email());

        $accessoryAcceptance = CheckoutAcceptance::factory()->forAccessory()->pending()->create([
            'checkoutable_id' => $accessory->id,
            'assigned_to_id' => $user->id,
        ]);

        $assetAcceptance = CheckoutAcceptance::factory()->pending()->create([
            'checkoutable_id' => $asset->id,
            'assigned_to_id' => $user->id,
        ]);

        $this->checkInAccessoryFrom($accessory, $user);

        Mail::assertSent(CheckinAccessoryMail::class);

        $this->assertAcceptanceWasSoftDeleted(
            $accessoryAcceptance,
            'The checked-in accessory\'s own pending acceptance should have been soft-deleted.'
        );

        $this->assertNull(
            CheckoutAcceptance::withTrashed()->findOrFail($assetAcceptance->id)->deleted_at,
            'Checking in the accessory should have left the asset acceptance alone. It was soft-deleted too, because the listener matches on checkoutable_id and assigned_to_id without filtering on checkoutable_type, and the asset happens to share the accessory\'s id.'
        );
    }

    private function checkInAccessoryFrom(Accessory $accessory, User $user): void
    {
        $checkout = $accessory->checkouts()
            ->where('assigned_type', User::class)
            ->where('assigned_to', $user->id)
            ->firstOrFail();

        $this->actingAs(User::factory()->checkinAccessories()->create())
            ->post(route('accessories.checkin.store', $checkout->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('accessories_checkout', ['id' => $checkout->id]);
    }

    /**
     * The missing morph filter only shows up when an accessory and an asset
     * share a primary key, and factories won't hand out a matching pair by
     * accident. Nudge whichever sequence is behind until the ids line up.
     *
     * @return array{0: Accessory, 1: Asset}
     */
    private function createAccessoryAndAssetSharingAnId(User $user): array
    {
        $accessory = Accessory::factory()->checkedOutToUser($user)->create();
        $asset = Asset::factory()->create();

        for ($attempt = 0; $accessory->id !== $asset->id && $attempt < 25; $attempt++) {
            if ($accessory->id < $asset->id) {
                $accessory = Accessory::factory()->checkedOutToUser($user)->create();
            } else {
                $asset = Asset::factory()->create();
            }
        }

        $this->assertSame(
            $accessory->id,
            $asset->id,
            'Fixture setup failed: could not get an accessory and an asset onto the same id.'
        );

        return [$accessory, $asset];
    }

    private function assertNoNotificationChannelIsConfigured(Accessory $accessory): void
    {
        $this->assertFalse((bool) $accessory->fresh()->checkin_email());
        $this->assertEmpty(Setting::getSettings()->admin_cc_email);
        $this->assertEmpty(Setting::getSettings()->webhook_endpoint);
    }

    private function assertAcceptanceWasSoftDeleted(CheckoutAcceptance $acceptance, string $message): void
    {
        $this->assertNotNull(
            CheckoutAcceptance::withTrashed()->findOrFail($acceptance->id)->deleted_at,
            $message
        );
    }
}
