<?php

namespace Tests\Feature\Console;

use App\Mail\ReacceptanceRequestMail;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutAcceptance;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class SendReacceptanceRequestsTest extends TestCase
{
    private const TYPES_LABEL = 'Which item types would you like to regenerate acceptances for?';

    private const CATEGORIES_GATE_LABEL = 'Limit to specific categories?';

    private const CATEGORIES_SEARCH_LABEL = 'Search for categories to include.';

    private const COMPANY_GATE_LABEL = 'Filter to a specific company?';

    private const USER_GATE_LABEL = 'Limit to a single user?';

    private const ACCEPTED_BEFORE_GATE_LABEL = 'Only include items accepted before a cutoff date?';

    private const BREAKDOWN_LABEL = 'Show a breakdown of the affected users and items?';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_regenerates_acceptance_for_previously_accepted_item_still_assigned(): void
    {
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
        $user = User::factory()->create();
        $this->acceptedAssetFor($user);

        $this->answerFilterPrompts($this->artisan('snipeit:send-reacceptance-requests'))
            ->expectsConfirmation('Is this a dry run?', 'no')
            ->expectsConfirmation('Regenerate 1 acceptances for 1 users?', 'yes')
            ->expectsConfirmation('Send the re-acceptance emails now?', 'yes')
            ->assertExitCode(0);

        Mail::assertSent(ReacceptanceRequestMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_interactive_breakdown_confirm_renders_the_item_list(): void
    {
        $user = User::factory()->create();
        $asset = $this->acceptedAssetFor($user)->checkoutable;

        $this->answerFilterPrompts($this->artisan('snipeit:send-reacceptance-requests'), showBreakdown: true)
            ->expectsOutputToContain('Asset #'.$asset->id)
            ->expectsConfirmation('Is this a dry run?', 'yes')
            ->assertExitCode(0);
    }

    public function test_interactive_decline_to_regenerate_writes_nothing(): void
    {
        $acceptance = $this->acceptedAssetFor(User::factory()->create());

        $this->answerFilterPrompts($this->artisan('snipeit:send-reacceptance-requests'))
            ->expectsConfirmation('Is this a dry run?', 'no')
            ->expectsConfirmation('Regenerate 1 acceptances for 1 users?', 'no')
            ->assertExitCode(0);

        $this->assertEquals(0, CheckoutAcceptance::pending()->count());
        $acceptance->refresh();
        $this->assertNull($acceptance->superseded_by_id);
    }

    public function test_interactive_dry_run_confirm_writes_nothing(): void
    {
        $user = User::factory()->create();
        $acceptance = $this->acceptedAssetFor($user);

        $this->answerFilterPrompts($this->artisan('snipeit:send-reacceptance-requests'))
            ->expectsConfirmation('Is this a dry run?', 'yes')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertEquals(0, CheckoutAcceptance::pending()->count());
        $acceptance->refresh();
        $this->assertNull($acceptance->superseded_by_id);
        $this->assertDatabaseMissing('action_logs', ['action_type' => 'reacceptance requested']);
    }

    public function test_interactive_send_flag_skips_send_prompt_and_emails(): void
    {
        $user = User::factory()->create();
        $this->acceptedAssetFor($user);

        // --send passed in an interactive run: the "Send…now?" confirm is skipped
        // and the flag value is honored (the regenerate confirm still shows).
        $this->answerFilterPrompts($this->artisan('snipeit:send-reacceptance-requests', ['--send' => true]))
            ->expectsConfirmation('Is this a dry run?', 'no')
            ->expectsConfirmation('Regenerate 1 acceptances for 1 users?', 'yes')
            ->assertExitCode(0);

        Mail::assertSent(ReacceptanceRequestMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_interactive_no_send_flag_skips_send_prompt_and_regenerates_without_email(): void
    {
        $user = User::factory()->create();
        $acceptance = $this->acceptedAssetFor($user);

        $this->answerFilterPrompts($this->artisan('snipeit:send-reacceptance-requests', ['--no-send' => true]))
            ->expectsConfirmation('Is this a dry run?', 'no')
            ->expectsConfirmation('Regenerate 1 acceptances for 1 users?', 'yes')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $acceptance->refresh();
        $this->assertNotNull($acceptance->superseded_by_id);
    }

    public function test_interactive_dry_run_flag_skips_dry_run_confirm_and_writes_nothing(): void
    {
        $user = User::factory()->create();
        $acceptance = $this->acceptedAssetFor($user);

        // --dry-run passed: the "Is this a dry run?" confirm is skipped (forced true).
        $this->answerFilterPrompts($this->artisan('snipeit:send-reacceptance-requests', ['--dry-run' => true]))
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertEquals(0, CheckoutAcceptance::pending()->count());
        $acceptance->refresh();
        $this->assertNull($acceptance->superseded_by_id);
    }

    public function test_interactive_accepted_before_flag_skips_its_gate_and_proceeds(): void
    {
        $user = User::factory()->create();
        $this->acceptedAssetFor($user, ['accepted_at' => now()->subYear()]);

        // --accepted-before passed: its wizard gate is skipped (option present).
        $this->answerFilterPrompts(
            $this->artisan('snipeit:send-reacceptance-requests', [
                '--accepted-before' => now()->subMonth()->format('Y-m-d'),
            ]),
            skipAcceptedBeforeGate: true,
        )
            ->expectsConfirmation('Is this a dry run?', 'no')
            ->expectsConfirmation('Regenerate 1 acceptances for 1 users?', 'yes')
            ->expectsConfirmation('Send the re-acceptance emails now?', 'yes')
            ->assertExitCode(0);

        Mail::assertSent(ReacceptanceRequestMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_non_interactive_without_accepted_before_does_not_block(): void
    {
        $user = User::factory()->create();
        $acceptance = $this->acceptedAssetFor($user);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        $acceptance->refresh();
        $this->assertNotNull($acceptance->superseded_by_id);
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

    public function test_regenerates_acceptance_for_accessory_still_assigned(): void
    {
        $user = User::factory()->create();
        $acceptance = $this->acceptedAccessoryFor($user);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        $newAcceptance = CheckoutAcceptance::where('checkoutable_type', Accessory::class)
            ->where('checkoutable_id', $acceptance->checkoutable_id)
            ->where('assigned_to_id', $user->id)
            ->pending()
            ->first();
        $this->assertNotNull($newAcceptance);

        $acceptance->refresh();
        $this->assertEquals($newAcceptance->id, $acceptance->superseded_by_id);
        $this->assertNotNull($acceptance->superseded_at);
    }

    public function test_regenerates_acceptance_for_consumable_still_assigned(): void
    {
        $user = User::factory()->create();
        $acceptance = $this->acceptedConsumableFor($user);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        $newAcceptance = CheckoutAcceptance::where('checkoutable_type', Consumable::class)
            ->where('checkoutable_id', $acceptance->checkoutable_id)
            ->where('assigned_to_id', $user->id)
            ->pending()
            ->first();
        $this->assertNotNull($newAcceptance);

        $acceptance->refresh();
        $this->assertEquals($newAcceptance->id, $acceptance->superseded_by_id);
        $this->assertNotNull($acceptance->superseded_at);
    }

    public function test_category_filter_limits_to_items_in_the_given_category(): void
    {
        $user = User::factory()->create();

        // An asset whose model belongs to the wanted category.
        $category = Category::factory()->create();
        $model = AssetModel::factory()->create(['category_id' => $category->id]);
        $wantedAsset = Asset::factory()->create(['model_id' => $model->id]);
        $wantedAcceptance = CheckoutAcceptance::factory()
            ->accepted()
            ->for($wantedAsset, 'checkoutable')
            ->for($user, 'assignedTo')
            ->create();

        // Control: a different (random) category.
        $otherAcceptance = $this->acceptedAssetFor($user);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--category' => [$category->id],
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        $wantedAcceptance->refresh();
        $otherAcceptance->refresh();
        $this->assertNotNull($wantedAcceptance->superseded_by_id);
        $this->assertNull($otherAcceptance->superseded_by_id, 'asset in another category should be excluded by --category');
    }

    public function test_company_filter_limits_to_items_in_the_given_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $inCompanyAsset = Asset::factory()->create(['company_id' => $company->id]);
        $inCompanyAcceptance = CheckoutAcceptance::factory()
            ->accepted()
            ->for($inCompanyAsset, 'checkoutable')
            ->for($user, 'assignedTo')
            ->create();

        // Control: a different company.
        $otherAcceptance = $this->acceptedAssetFor($user);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--company' => $company->id,
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        $inCompanyAcceptance->refresh();
        $otherAcceptance->refresh();
        $this->assertNotNull($inCompanyAcceptance->superseded_by_id);
        $this->assertNull($otherAcceptance->superseded_by_id, 'asset in another company should be excluded by --company');
    }

    public function test_user_filter_limits_to_the_given_user(): void
    {
        $targetUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $targetAcceptance = $this->acceptedAssetFor($targetUser);
        $otherAcceptance = $this->acceptedAssetFor($otherUser);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--user' => $targetUser->id,
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        $targetAcceptance->refresh();
        $otherAcceptance->refresh();
        $this->assertNotNull($targetAcceptance->superseded_by_id);
        $this->assertNull($otherAcceptance->superseded_by_id, 'other user should be excluded by --user');
    }

    public function test_sends_one_email_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->acceptedAssetFor($userA);
        $this->acceptedAssetFor($userB);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--send' => true,
        ])->assertExitCode(0);

        Mail::assertSent(ReacceptanceRequestMail::class, 2);
        Mail::assertSent(ReacceptanceRequestMail::class, fn ($mail) => $mail->hasTo($userA->email));
        Mail::assertSent(ReacceptanceRequestMail::class, fn ($mail) => $mail->hasTo($userB->email));
    }

    public function test_groups_multiple_items_for_one_user_into_a_single_email(): void
    {
        $user = User::factory()->create();
        $this->acceptedAssetFor($user);
        $this->acceptedAssetFor($user);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--send' => true,
        ])->assertExitCode(0);

        // One email for the user, carrying both new acceptances.
        Mail::assertSent(ReacceptanceRequestMail::class, 1);
        Mail::assertSent(ReacceptanceRequestMail::class, fn ($mail) => $mail->acceptances->count() === 2);
    }

    public function test_send_and_no_send_together_errors(): void
    {
        $this->acceptedAssetFor(User::factory()->create());

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--send' => true,
            '--no-send' => true,
        ])->assertExitCode(1);

        $this->assertEquals(0, CheckoutAcceptance::pending()->count());
    }

    public function test_no_candidates_reports_nothing_to_do(): void
    {
        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])
            ->expectsOutput('No items need re-acceptance.')
            ->assertExitCode(0);
    }

    public function test_interactive_confirm_regenerate_decline_send_then_create_without_email(): void
    {
        $user = User::factory()->create();
        $acceptance = $this->acceptedAssetFor($user);

        $this->answerFilterPrompts($this->artisan('snipeit:send-reacceptance-requests'))
            ->expectsConfirmation('Is this a dry run?', 'no')
            ->expectsConfirmation('Regenerate 1 acceptances for 1 users?', 'yes')
            ->expectsConfirmation('Send the re-acceptance emails now?', 'no')
            ->expectsConfirmation('Continue and create the acceptances WITHOUT sending emails?', 'yes')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('checkout_acceptances', [
            'checkoutable_id' => $acceptance->checkoutable_id,
            'assigned_to_id' => $user->id,
            'accepted_at' => null,
            'superseded_by_id' => null,
        ]);
    }

    public function test_interactive_decline_send_and_decline_continue_aborts(): void
    {
        $acceptance = $this->acceptedAssetFor(User::factory()->create());

        $this->answerFilterPrompts($this->artisan('snipeit:send-reacceptance-requests'))
            ->expectsConfirmation('Is this a dry run?', 'no')
            ->expectsConfirmation('Regenerate 1 acceptances for 1 users?', 'yes')
            ->expectsConfirmation('Send the re-acceptance emails now?', 'no')
            ->expectsConfirmation('Continue and create the acceptances WITHOUT sending emails?', 'no')
            ->assertExitCode(0);

        $this->assertEquals(0, CheckoutAcceptance::pending()->count());
        $acceptance->refresh();
        $this->assertNull($acceptance->superseded_by_id);
    }

    public function test_supersedes_all_prior_accepted_acceptances_for_the_same_item(): void
    {
        $user = User::factory()->create();
        $first = $this->acceptedAssetFor($user, ['accepted_at' => now()->subYear()]);
        $asset = $first->checkoutable;

        // A second accepted acceptance for the SAME asset/user.
        $second = CheckoutAcceptance::factory()
            ->accepted()
            ->for($asset, 'checkoutable')
            ->for($user, 'assignedTo')
            ->create(['accepted_at' => now()->subMonth()]);

        $this->artisan('snipeit:send-reacceptance-requests', [
            '--no-interaction' => true,
            '--force' => true,
            '--no-send' => true,
        ])->assertExitCode(0);

        // Exactly one new pending acceptance for the item/user.
        $newPending = CheckoutAcceptance::where('checkoutable_type', Asset::class)
            ->where('checkoutable_id', $asset->id)
            ->where('assigned_to_id', $user->id)
            ->pending()
            ->get();
        $this->assertCount(1, $newPending);

        // Both prior accepted rows are superseded by that single new acceptance.
        $first->refresh();
        $second->refresh();
        $this->assertEquals($newPending->first()->id, $first->superseded_by_id);
        $this->assertEquals($newPending->first()->id, $second->superseded_by_id);
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

    private function acceptedAccessoryFor(User $user, array $attributes = []): CheckoutAcceptance
    {
        // Accessories are not auto-assigned by the factory, so check one out to
        // the user explicitly to make it "still assigned" for the resolver.
        $accessory = Accessory::factory()->create();

        AccessoryCheckout::factory()->create([
            'accessory_id' => $accessory->id,
            'assigned_to' => $user->id,
            'assigned_type' => User::class,
        ]);

        return CheckoutAcceptance::factory()
            ->accepted()
            ->for($accessory, 'checkoutable')
            ->for($user, 'assignedTo')
            ->create($attributes);
    }

    private function acceptedConsumableFor(User $user, array $attributes = []): CheckoutAcceptance
    {
        // Consumables are not auto-assigned by the factory, so attach the user
        // explicitly to make it "still assigned" for the resolver.
        $consumable = Consumable::factory()->create();
        $consumable->users()->attach($user->id, ['created_by' => $user->id]);

        return CheckoutAcceptance::factory()
            ->accepted()
            ->for($consumable, 'checkoutable')
            ->for($user, 'assignedTo')
            ->create($attributes);
    }

    /**
     * Answer the interactive filter prompts (in order) with "no filter": all four
     * types, and the categories/company/user/accepted-before gates declined. Then
     * answers the post-preview "show breakdown?" offer. Returns the PendingCommand
     * so callers can append the remaining execution confirms (dry-run, regenerate,
     * send).
     *
     * @param  string[]  $typeTokens  selected type tokens; empty selection => all four
     * @param  int[]  $categoryIds  selected category ids; empty => decline the gate
     */
    private function answerFilterPrompts(
        PendingCommand $command,
        array $typeTokens = [],
        array $categoryIds = [],
        ?Company $company = null,
        ?User $user = null,
        ?string $acceptedBefore = null,
        bool $skipTypes = false,
        bool $skipCategoriesGate = false,
        bool $skipCompanyGate = false,
        bool $skipUserGate = false,
        bool $skipAcceptedBeforeGate = false,
        bool $showBreakdown = false,
    ): PendingCommand {
        // 1. types multiselect (empty selection => all four). Skipped when --type passed.
        if (! $skipTypes) {
            $command->expectsQuestion(self::TYPES_LABEL, $typeTokens);
        }

        // 2. categories gate confirm -> multisearch only when answered yes. The
        //    multisearch falls back as a search term ask() then the selection.
        //    Skipped entirely when --category passed.
        if (! $skipCategoriesGate) {
            $command->expectsConfirmation(self::CATEGORIES_GATE_LABEL, $categoryIds === [] ? 'no' : 'yes');
        }
        if (! $skipCategoriesGate && $categoryIds !== []) {
            $command->expectsQuestion(self::CATEGORIES_SEARCH_LABEL, '');
            $command->expectsQuestion(self::CATEGORIES_SEARCH_LABEL, $categoryIds);
        }

        // 3. company gate confirm -> search only when answered yes. The search term
        //    must match what the command's option closure queries on (company name).
        //    Skipped entirely when --company passed.
        if (! $skipCompanyGate) {
            $command->expectsConfirmation(self::COMPANY_GATE_LABEL, $company === null ? 'no' : 'yes');
        }
        if (! $skipCompanyGate && $company !== null) {
            $command->expectsSearch(
                'Search for a company by name.',
                $company->id,
                $company->name,
                [$company->id => "{$company->name} (ID: {$company->id})"],
            );
        }

        // 4. user gate confirm -> search only when answered yes. Skipped when --user passed.
        if (! $skipUserGate) {
            $command->expectsConfirmation(self::USER_GATE_LABEL, $user === null ? 'no' : 'yes');
        }
        if (! $skipUserGate && $user !== null) {
            $command->expectsSearch(
                'Search for a user by username, first or last name.',
                $user->id,
                $user->username,
                [$user->id => "{$user->first_name} {$user->last_name} ({$user->username})"],
            );
        }

        // 5. accepted-before gate confirm -> validated text only when answered yes.
        //    Skipped when --accepted-before passed.
        if (! $skipAcceptedBeforeGate) {
            $command->expectsConfirmation(self::ACCEPTED_BEFORE_GATE_LABEL, $acceptedBefore === null ? 'no' : 'yes');
        }
        if (! $skipAcceptedBeforeGate && $acceptedBefore !== null) {
            $command->expectsQuestion('Accepted-before cutoff date (Y-m-d):', $acceptedBefore);
        }

        // 6. After the preview, interactive runs are offered the per-user breakdown.
        $command->expectsConfirmation(self::BREAKDOWN_LABEL, $showBreakdown ? 'yes' : 'no');

        return $command;
    }
}
