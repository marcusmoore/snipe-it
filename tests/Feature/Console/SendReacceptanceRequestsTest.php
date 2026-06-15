<?php

namespace Tests\Feature\Console;

use App\Mail\ReacceptanceRequestMail;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendReacceptanceRequestsTest extends TestCase
{
    public function test_regenerates_acceptance_for_previously_accepted_item_still_assigned(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $acceptedAcceptance = $this->acceptedAssetFor($user);
        $asset = $acceptedAcceptance->checkoutable;

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        // A fresh pending acceptance now exists for the same item/user.
        $newAcceptance = CheckoutAcceptance::where('checkoutable_type', Asset::class)
            ->where('checkoutable_id', $asset->id)
            ->where('assigned_to_id', $user->id)
            ->pending()
            ->first();
        $this->assertNotNull($newAcceptance);

        // The old accepted row is superseded by the new one.
        $acceptedAcceptance->refresh();
        $this->assertEquals($newAcceptance->id, $acceptedAcceptance->superseded_by_id);
        $this->assertNotNull($acceptedAcceptance->superseded_at);
    }

    public function test_writes_one_reacceptance_requested_action_log_per_item_with_null_cli_actor(): void
    {
        $user = User::factory()->create();
        $acceptedAcceptance = $this->acceptedAssetFor($user);
        $asset = $acceptedAcceptance->checkoutable;

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('action_logs', [
            'action_type' => 'reacceptance requested',
            'item_type' => Asset::class,
            'item_id' => $asset->id,
            'target_type' => User::class,
            'target_id' => $user->id,
            'created_by' => null,
        ]);
    }

    public function test_license_seat_is_logged_against_its_parent_license(): void
    {
        $user = User::factory()->create();
        $license = License::factory()->create();
        $licenseSeat = LicenseSeat::factory()->for($license)->create(['assigned_to' => $user->id]);

        CheckoutAcceptance::factory()
            ->accepted()
            ->for($licenseSeat, 'checkoutable')
            ->for($user, 'assignedTo')
            ->create();

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('action_logs', [
            'action_type' => 'reacceptance requested',
            'item_type' => License::class,
            'item_id' => $license->id,
            'target_id' => $user->id,
        ]);
    }

    public function test_sends_one_email_per_user_when_sending(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->acceptedAssetFor($user);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--send' => true,
        ])->assertExitCode(0);

        Mail::assertSent(ReacceptanceRequestMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_no_send_does_not_email(): void
    {
        Mail::fake();
        $this->acceptedAssetFor(User::factory()->create());

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        Mail::assertNothingSent();
    }

    public function test_users_without_email_are_skipped_from_send_but_still_regenerated(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => null]);
        $acceptance = $this->acceptedAssetFor($user);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--send' => true,
        ])->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('checkout_acceptances', [
            'checkoutable_id' => $acceptance->checkoutable_id,
            'assigned_to_id' => $user->id,
            'accepted_at' => null,
            'superseded_by_id' => null,
        ]);
    }

    public function test_excludes_items_no_longer_assigned_to_the_same_user(): void
    {
        $user = User::factory()->create();
        $acceptance = $this->acceptedAssetFor($user);

        // Reassign the asset away from the original user.
        $acceptance->checkoutable->update(['assigned_to' => User::factory()->create()->id]);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        // No new pending acceptance, old one untouched.
        $this->assertEquals(0, CheckoutAcceptance::pending()->count());
        $acceptance->refresh();
        $this->assertNull($acceptance->superseded_by_id);
    }

    public function test_accepted_before_filter_scopes_to_older_acceptances(): void
    {
        $user = User::factory()->create();
        $oldAcceptance = $this->acceptedAssetFor($user, ['accepted_at' => now()->subYear()]);
        $recentAcceptance = $this->acceptedAssetFor($user, ['accepted_at' => now()->subDay()]);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--accepted-before' => now()->subMonth()->format('Y-m-d'),
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        $oldAcceptance->refresh();
        $recentAcceptance->refresh();
        $this->assertNotNull($oldAcceptance->superseded_by_id);
        $this->assertNull($recentAcceptance->superseded_by_id);
    }

    public function test_type_filter_limits_to_the_given_type(): void
    {
        $user = User::factory()->create();
        $assetAcceptance = $this->acceptedAssetFor($user);

        $license = License::factory()->create();
        $licenseSeat = LicenseSeat::factory()->for($license)->create(['assigned_to' => $user->id]);
        $licenseAcceptance = CheckoutAcceptance::factory()
            ->accepted()
            ->for($licenseSeat, 'checkoutable')
            ->for($user, 'assignedTo')
            ->create();

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--type' => ['license'],
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        $assetAcceptance->refresh();
        $licenseAcceptance->refresh();
        $this->assertNull($assetAcceptance->superseded_by_id, 'asset should be excluded by --type=license');
        $this->assertNotNull($licenseAcceptance->superseded_by_id);
    }

    public function test_unknown_type_token_errors(): void
    {
        $this->artisan('snipeit:send-reacceptance-requests', [
            '--type' => ['widget'],
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(1);
    }

    public function test_dry_run_writes_nothing_and_sends_nothing(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $acceptance = $this->acceptedAssetFor($user);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--dry-run' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertEquals(0, CheckoutAcceptance::pending()->count());
        $acceptance->refresh();
        $this->assertNull($acceptance->superseded_by_id);
        $this->assertDatabaseMissing('action_logs', ['action_type' => 'reacceptance requested']);
    }

    public function test_non_interactive_without_force_errors_and_writes_nothing(): void
    {
        $acceptance = $this->acceptedAssetFor(User::factory()->create());

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--no-send' => true,
        ])->assertExitCode(1);

        $this->assertEquals(0, CheckoutAcceptance::pending()->count());
    }

    public function test_non_interactive_without_send_choice_errors(): void
    {
        $this->acceptedAssetFor(User::factory()->create());

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
        ])->assertExitCode(1);
    }

    public function test_interactive_confirm_then_send_regenerates_and_emails(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->acceptedAssetFor($user);

        $this->artisan('snipeit:send-reacceptance-requests')
            ->expectsConfirmation('Regenerate 1 acceptances for 1 users?', 'yes')
            ->expectsConfirmation('Send the re-acceptance emails now?', 'yes')
            ->assertExitCode(0);

        Mail::assertSent(ReacceptanceRequestMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_interactive_decline_to_regenerate_writes_nothing(): void
    {
        $acceptance = $this->acceptedAssetFor(User::factory()->create());

        $this->artisan('snipeit:send-reacceptance-requests')
            ->expectsConfirmation('Regenerate 1 acceptances for 1 users?', 'no')
            ->assertExitCode(0);

        $this->assertEquals(0, CheckoutAcceptance::pending()->count());
        $acceptance->refresh();
        $this->assertNull($acceptance->superseded_by_id);
    }

    public function test_re_run_is_idempotent_while_the_new_acceptance_is_pending(): void
    {
        $user = User::factory()->create();
        $this->acceptedAssetFor($user);

        $params = [
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ];

        $this->artisan('snipeit:send-reacceptance-requests', $params)->assertExitCode(0);
        $afterFirstRun = CheckoutAcceptance::count();

        $this->artisan('snipeit:send-reacceptance-requests', $params)->assertExitCode(0);

        $this->assertEquals($afterFirstRun, CheckoutAcceptance::count(), 'second run should not create more acceptances');
    }

    private function acceptedAssetFor(User $user, array $attributes = []): CheckoutAcceptance
    {
        // The factory's afterCreating assigns the asset to the user, so it is
        // "still assigned" for the resolver.
        return CheckoutAcceptance::factory()
            ->accepted()
            ->for(Asset::factory(), 'checkoutable')
            ->for($user, 'assignedTo')
            ->create($attributes);
    }
}
