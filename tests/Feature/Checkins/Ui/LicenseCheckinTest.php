<?php

namespace Tests\Feature\Checkins\Ui;

use App\Events\CheckoutableCheckedIn;
use App\Mail\CheckinLicenseMail;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CheckoutAcceptance;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CheckinLicenseSeatNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LicenseCheckinTest extends TestCase
{
    public function test_checking_in_license_requires_correct_permission()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('licenses.checkin.save', [
                'licenseId' => LicenseSeat::factory()->assignedToUser()->create()->id,
            ]))
            ->assertForbidden();
    }

    public function test_non_reassignable_seat_remains_unreassignable_after_checkin()
    {
        $licenseSeat = LicenseSeat::factory()
            ->notReassignable()
            ->assignedToUser()
            ->create();

        $this->actingAs(User::factory()->checkinLicenses()->create())
            ->post(route('licenses.checkin.save', $licenseSeat));

        $licenseSeat->refresh();

        $this->assertEquals(true, $licenseSeat->unreassignable_seat);
    }

    public function test_cannot_checkin_license_that_is_not_assigned()
    {
        $licenseSeat = LicenseSeat::factory()
            ->reassignable()
            ->create();

        $this->assertNull($licenseSeat->assigned_to);
        $this->assertNull($licenseSeat->asset_id);

        $this->actingAs(User::factory()->checkinLicenses()->create())
            ->post(route('licenses.checkin.save', $licenseSeat), [
                'notes' => 'my note',
                'redirect_option' => 'index',
            ])
            ->assertSessionHas('error', trans('admin/licenses/message.checkin.error'));
    }

    public function test_can_check_in_license_assigned_to_asset()
    {
        Event::fake([CheckoutableCheckedIn::class]);

        $asset = Asset::factory()->create();

        $licenseSeat = LicenseSeat::factory()
            ->reassignable()
            ->assignedToAsset($asset)
            ->create();

        $actor = User::factory()->checkinLicenses()->create();

        $this->actingAs($actor)
            ->post(route('licenses.checkin.save', $licenseSeat), [
                'notes' => 'my note',
                'redirect_option' => 'index',
            ])
            ->assertRedirect(route('licenses.index'));

        $this->assertNull($licenseSeat->fresh()->asset_id);
        $this->assertNull($licenseSeat->fresh()->assigned_to);
        $this->assertEquals('my note', $licenseSeat->fresh()->notes);

        Event::assertDispatchedTimes(CheckoutableCheckedIn::class, 1);
        Event::assertDispatched(CheckoutableCheckedIn::class, function (CheckoutableCheckedIn $event) use ($actor, $asset, $licenseSeat) {
            return $event->checkoutable->is($licenseSeat)
                && $event->checkedOutTo->is($asset)
                && $event->checkedInBy->is($actor)
                && $event->note === 'my note';
        });
    }

    public function test_can_check_in_license_assigned_to_user()
    {
        Event::fake([CheckoutableCheckedIn::class]);

        $user = User::factory()->create();

        $licenseSeat = LicenseSeat::factory()
            ->reassignable()
            ->assignedToUser($user)
            ->create();

        $actor = User::factory()->checkinLicenses()->create();

        $this->actingAs($actor)
            ->post(route('licenses.checkin.save', $licenseSeat), [
                'notes' => 'my note',
                'redirect_option' => 'index',
            ])
            ->assertRedirect(route('licenses.index'));

        $this->assertNull($licenseSeat->fresh()->asset_id);
        $this->assertNull($licenseSeat->fresh()->assigned_to);
        $this->assertEquals('my note', $licenseSeat->fresh()->notes);

        Event::assertDispatchedTimes(CheckoutableCheckedIn::class, 1);
        Event::assertDispatched(CheckoutableCheckedIn::class, function (CheckoutableCheckedIn $event) use ($actor, $licenseSeat, $user) {
            return $event->checkoutable->is($licenseSeat)
                && $event->checkedOutTo->is($user)
                && $event->checkedInBy->is($actor)
                && $event->note === 'my note';
        });

    }

    public function test_page_renders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('licenses.checkin', LicenseSeat::factory()->assignedToUser()->create()->id))
            ->assertOk();

    }

    public function test_license_seat_checkin_clears_pending_acceptance_when_notifications_are_fully_disabled()
    {
        $this->settings->disableAdminCC()->disableAdminCCAlways()->disableSlackWebhook();

        $user = User::factory()->create();
        $seat = $this->seatAssignedTo($user);

        $acceptance = $this->pendingSeatAcceptance($seat, $user);

        $this->assertNoNotificationChannelIsConfigured($seat);

        $this->checkInSeat($seat);

        $this->assertAcceptanceWasSoftDeleted(
            $acceptance,
            'The pending acceptance should have been soft-deleted when the seat was checked in, but it survived: with no email and no webhook configured the listener returns before it reaches the delete.'
        );
    }

    public function test_license_seat_checkin_clears_pending_acceptance_when_only_a_webhook_is_configured()
    {
        Notification::fake();

        $this->settings->disableAdminCC()->disableAdminCCAlways()->enableSlackWebhook();

        $user = User::factory()->create();
        $seat = $this->seatAssignedTo($user);

        $acceptance = $this->pendingSeatAcceptance($seat, $user);

        $this->checkInSeat($seat);

        $this->assertSlackNotificationSent(CheckinLicenseSeatNotification::class);

        $this->assertAcceptanceWasSoftDeleted(
            $acceptance,
            'The pending acceptance should have been soft-deleted when the seat was checked in, but it survived: a webhook-only install never enters the notification branch that holds the delete.'
        );
    }

    public function test_license_seat_checkin_does_not_clear_a_same_id_pending_acceptance_of_another_type()
    {
        Mail::fake();

        $this->settings->disableAdminCC()->disableAdminCCAlways()->disableSlackWebhook();

        $user = User::factory()->create();

        [$seat, $asset] = $this->createSeatAndAssetSharingAnId($user);

        // Notifications on for this one. With checkin_email off the delete never
        // runs at all and this would be a re-run of the two tests above rather
        // than a test of the missing morph filter.
        $seat->license->category->update(['checkin_email' => true]);
        $this->assertTrue((bool) $seat->license->fresh()->checkin_email());

        $seatAcceptance = $this->pendingSeatAcceptance($seat, $user);

        $assetAcceptance = CheckoutAcceptance::factory()->pending()->create([
            'checkoutable_id' => $asset->id,
            'assigned_to_id' => $user->id,
        ]);
        $this->assertSame(Asset::class, $assetAcceptance->checkoutable_type);

        $this->checkInSeat($seat);

        Mail::assertSent(CheckinLicenseMail::class);

        $this->assertAcceptanceWasSoftDeleted(
            $seatAcceptance,
            'The checked-in seat\'s own pending acceptance should have been soft-deleted.'
        );

        $this->assertNull(
            CheckoutAcceptance::withTrashed()->findOrFail($assetAcceptance->id)->deleted_at,
            'Checking in the license seat should have left the asset acceptance alone. It was soft-deleted too, because the listener matches on checkoutable_id and assigned_to_id without filtering on checkoutable_type, and the asset happens to share the seat\'s id.'
        );
    }

    /**
     * Acceptance required, checkin email off — the configuration where a stale
     * pending acceptance can outlive the assignment it belongs to.
     */
    private function seatAssignedTo(User $user): LicenseSeat
    {
        $license = License::factory()->create([
            'reassignable' => 1,
            'category_id' => Category::factory()->create([
                'category_type' => 'license',
                'require_acceptance' => 1,
                'checkin_email' => 0,
            ])->id,
        ]);

        return LicenseSeat::factory()
            ->assignedToUser($user)
            ->create(['license_id' => $license->id]);
    }

    /**
     * A real seat acceptance is keyed to the SEAT — LicenseSeat::class plus the
     * seat's own id — which is what CreateCheckoutAcceptanceAction writes. There
     * is no morph map aliasing License and LicenseSeat.
     */
    private function pendingSeatAcceptance(LicenseSeat $seat, User $user): CheckoutAcceptance
    {
        $acceptance = CheckoutAcceptance::factory()
            ->forLicenseSeat()
            ->pending()
            ->create([
                'checkoutable_id' => $seat->id,
                'assigned_to_id' => $user->id,
            ]);

        $this->assertSame(LicenseSeat::class, $acceptance->checkoutable_type);
        $this->assertTrue($acceptance->isPending());

        return $acceptance;
    }

    private function checkInSeat(LicenseSeat $seat): void
    {
        $this->actingAs(User::factory()->checkinLicenses()->create())
            ->post(route('licenses.checkin.save', $seat->id))
            ->assertRedirect();

        // Confirm the checkin actually happened before asserting anything about
        // what it did or didn't clean up.
        $this->assertNull($seat->refresh()->assigned_to, 'The seat was not checked in, so nothing downstream ran.');
    }

    /**
     * The missing morph filter only shows up when a seat and an asset share a
     * primary key, and factories won't hand out a matching pair by accident.
     * Nudge whichever sequence is behind until the ids line up.
     *
     * @return array{0: LicenseSeat, 1: Asset}
     */
    private function createSeatAndAssetSharingAnId(User $user): array
    {
        $seat = $this->seatAssignedTo($user);
        $asset = Asset::factory()->create();

        for ($attempt = 0; $seat->id !== $asset->id && $attempt < 25; $attempt++) {
            if ($seat->id < $asset->id) {
                $seat = $this->seatAssignedTo($user);
            } else {
                $asset = Asset::factory()->create();
            }
        }

        $this->assertSame(
            $seat->id,
            $asset->id,
            'Fixture setup failed: could not get a license seat and an asset onto the same id.'
        );

        return [$seat, $asset];
    }

    private function assertNoNotificationChannelIsConfigured(LicenseSeat $seat): void
    {
        $this->assertFalse((bool) $seat->license->checkin_email());
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
