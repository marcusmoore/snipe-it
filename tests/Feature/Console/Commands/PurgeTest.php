<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\License;
use App\Models\Location;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Broad coverage for snipeit:purge, split by resource. PurgeCompaniesTest
 * already covers Company. This file also locks in the "trashed-only,
 * non-trashed untouched" contract for the resources that carry child
 * action_log rows and other FK-linked children (asset maintenances,
 * license seats), plus the per-target-type action_log filtering that a
 * refactor could easily get wrong (target_id/target_type for users vs
 * item_id/item_type for everything else).
 */
class PurgeTest extends TestCase
{
    public function test_soft_deleted_asset_and_its_children_are_purged(): void
    {
        $trashed = Asset::factory()->create();
        $trashed->delete();
        $trashedLog = Actionlog::factory()->create([
            'item_type' => Asset::class,
            'item_id' => $trashed->id,
            'action_type' => 'checkout',
        ]);
        $trashedMaintenance = Maintenance::factory()->create(['asset_id' => $trashed->id]);

        $this->artisan('snipeit:purge', ['--force' => 'true'])->assertExitCode(0);

        $this->assertDatabaseMissing('assets', ['id' => $trashed->id]);
        $this->assertDatabaseMissing('action_logs', ['id' => $trashedLog->id]);
        $this->assertDatabaseMissing('maintenances', ['id' => $trashedMaintenance->id]);
    }

    public function test_live_asset_and_its_children_are_not_purged(): void
    {
        $live = Asset::factory()->create();
        $liveLog = Actionlog::factory()->create([
            'item_type' => Asset::class,
            'item_id' => $live->id,
            'action_type' => 'checkout',
        ]);
        $liveMaintenance = Maintenance::factory()->create(['asset_id' => $live->id]);

        $this->artisan('snipeit:purge', ['--force' => 'true'])->assertExitCode(0);

        $this->assertDatabaseHas('assets', ['id' => $live->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('action_logs', ['id' => $liveLog->id]);
        $this->assertDatabaseHas('maintenances', ['id' => $liveMaintenance->id]);
    }

    public function test_action_logs_for_a_different_item_type_at_same_id_are_not_deleted(): void
    {
        // This is the polymorphic-collision guard: a trashed Asset with
        // id=7 must not cause deletion of an Accessory action_log whose
        // item_id also happens to be 7.
        $trashedAsset = Asset::factory()->create();
        $trashedAsset->delete();
        $liveAccessory = Accessory::factory()->create();

        $assetLog = Actionlog::factory()->create([
            'item_type' => Asset::class,
            'item_id' => $trashedAsset->id,
            'action_type' => 'checkout',
        ]);
        $accessoryLogWithColliding_id = Actionlog::factory()->create([
            'item_type' => Accessory::class,
            'item_id' => $trashedAsset->id, // deliberately collides
            'action_type' => 'checkout',
        ]);

        $this->artisan('snipeit:purge', ['--force' => 'true'])->assertExitCode(0);

        $this->assertDatabaseMissing('action_logs', ['id' => $assetLog->id]);
        $this->assertDatabaseHas('action_logs', ['id' => $accessoryLogWithColliding_id->id]);
        $this->assertDatabaseHas('accessories', ['id' => $liveAccessory->id]);
    }

    public function test_soft_deleted_license_and_its_seats_are_purged(): void
    {
        $license = License::factory()->create(['seats' => 3]);
        // License::factory()->create() auto-generates the seats via observer;
        // confirm they exist before delete.
        $this->assertDatabaseHas('license_seats', ['license_id' => $license->id]);
        $license->delete();

        $this->artisan('snipeit:purge', ['--force' => 'true'])->assertExitCode(0);

        $this->assertDatabaseMissing('licenses', ['id' => $license->id]);
        $this->assertDatabaseMissing('license_seats', ['license_id' => $license->id]);
    }

    public function test_user_action_logs_use_target_type_target_id_not_item_type_item_id(): void
    {
        // User::userlog() reads action_logs.target_id/target_type — the
        // rest of the polymorphic children read item_id/item_type. A
        // refactor that only handled item_id/item_type would silently
        // leave user action_logs behind.
        $user = User::factory()->create();
        $user->delete();

        $userTargetLog = Actionlog::factory()->create([
            'target_type' => User::class,
            'target_id' => $user->id,
            'action_type' => 'update',
        ]);

        $this->artisan('snipeit:purge', ['--force' => 'true'])->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('action_logs', ['id' => $userTargetLog->id]);
    }

    public function test_soft_deleted_user_with_show_in_list_zero_is_preserved(): void
    {
        // System users (LDAP-sync placeholders, etc.) set show_in_list=0
        // and are excluded from purge even when soft-deleted. This filter
        // was in the pre-refactor code and must be preserved.
        $systemUser = User::factory()->create(['show_in_list' => 0]);
        $systemUser->delete();

        $this->artisan('snipeit:purge', ['--force' => 'true'])->assertExitCode(0);

        // Row is gone-from-index (soft-deleted) but still in the table.
        $this->assertDatabaseHas('users', ['id' => $systemUser->id]);
    }

    public function test_soft_deleted_location_is_purged(): void
    {
        $location = Location::factory()->create();
        $location->delete();

        $this->artisan('snipeit:purge', ['--force' => 'true'])->assertExitCode(0);

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_confirmation_prompt_declined_purges_nothing(): void
    {
        $trashed = Asset::factory()->create();
        $trashed->delete();

        $this->artisan('snipeit:purge')
            ->expectsConfirmation('Continue with the purge?', 'no')
            ->assertExitCode(0);

        // Row is still soft-deleted, not force-deleted.
        $this->assertDatabaseHas('assets', ['id' => $trashed->id]);
    }

    public function test_confirmation_prompt_confirmed_purges_records(): void
    {
        $trashed = Asset::factory()->create();
        $trashed->delete();

        $this->artisan('snipeit:purge')
            ->expectsConfirmation('Continue with the purge?', 'yes')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('assets', ['id' => $trashed->id]);
    }

    public function test_dry_run_does_not_delete_anything(): void
    {
        $trashedAsset = Asset::factory()->create();
        $trashedAsset->delete();
        $trashedLog = Actionlog::factory()->create([
            'item_type' => Asset::class,
            'item_id' => $trashedAsset->id,
            'action_type' => 'checkout',
        ]);

        $this->artisan('snipeit:purge', ['--force' => 'true', '--dry-run' => true])
            ->assertExitCode(0);

        // Everything is still in place.
        $this->assertNotNull(
            DB::table('assets')->where('id', $trashedAsset->id)->value('deleted_at'),
            'Trashed asset should still exist (soft-deleted) after dry-run'
        );
        $this->assertDatabaseHas('action_logs', ['id' => $trashedLog->id]);
    }
}
