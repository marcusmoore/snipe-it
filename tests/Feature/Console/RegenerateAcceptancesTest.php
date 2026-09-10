<?php

namespace Tests\Feature\Console;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutAcceptance;
use App\Models\Company;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\Location;
use App\Models\User;
use Database\Factories\CheckoutAcceptanceFactory;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class RegenerateAcceptancesTest extends TestCase
{
    public function test_asset_assigned_directly_to_a_user_is_a_candidate(): void
    {
        $holder = User::factory()->create();
        $asset = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsTable(
                ['User', 'Item', 'Type', 'Units held', 'Qty'],
                [[$holder->present()->fullName, $asset->present()->name, 'Asset', 1, 1]],
            )
            ->assertExitCode(0);
    }

    public function test_asset_assigned_to_another_asset_resolves_to_that_assets_holder(): void
    {
        $holder = User::factory()->create();
        $laptop = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);
        $dock = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $laptop->id,
            'assigned_type' => Asset::class,
        ]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutputToContain($dock->present()->name)
            ->assertExitCode(0);
    }

    public function test_asset_assigned_to_an_unassigned_asset_is_not_a_candidate(): void
    {
        $parked = $this->assetIn($this->acceptanceCategory('asset'));
        $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $parked->id,
            'assigned_type' => Asset::class,
        ]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutput('No users currently hold items requiring acceptance in that scope.')
            ->assertExitCode(0);
    }

    public function test_asset_assigned_to_a_location_is_not_a_candidate(): void
    {
        $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => Location::factory()->create()->id,
            'assigned_type' => Location::class,
        ]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutput('No users currently hold items requiring acceptance in that scope.')
            ->assertExitCode(0);
    }

    public function test_asset_in_a_category_not_requiring_acceptance_is_not_a_candidate(): void
    {
        $category = Category::factory()->create([
            'category_type' => 'asset',
            'require_acceptance' => false,
        ]);

        $this->assetIn($category, [
            'assigned_to' => User::factory()->create()->id,
            'assigned_type' => User::class,
        ]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutput('No users currently hold items requiring acceptance in that scope.')
            ->assertExitCode(0);
    }

    /**
     * `Asset::category()` is a hasOneThrough, so Laravel excludes assets whose AssetModel
     * is soft-deleted. A live checkout asks `requireAcceptance()`, which reads
     * `$this->model->category` through a `withTrashed()` belongsTo and creates the
     * acceptance row anyway — so the builder must reach the category the same way or it
     * regenerates a smaller set than a checkout creates.
     */
    public function test_asset_whose_model_is_soft_deleted_is_still_a_candidate(): void
    {
        $holder = User::factory()->create();
        $model = AssetModel::factory()->create(['category_id' => $this->acceptanceCategory('asset')->id]);
        $asset = Asset::factory()->create([
            'model_id' => $model->id,
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);

        $model->delete();

        $this->assertTrue((bool) $asset->fresh()->requireAcceptance());

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsTable(
                ['User', 'Item', 'Type', 'Units held', 'Qty'],
                [[$holder->present()->fullName, $asset->present()->name, 'Asset', 1, 1]],
            )
            ->assertExitCode(0);
    }

    public function test_soft_deleted_holders_are_not_candidates(): void
    {
        $holder = User::factory()->create();
        $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);
        $holder->delete();

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutput('No users currently hold items requiring acceptance in that scope.')
            ->assertExitCode(0);
    }

    public function test_license_seat_attached_only_to_an_asset_resolves_to_the_assets_holder(): void
    {
        $holder = User::factory()->create();
        $laptop = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);
        $license = License::factory()->create(['category_id' => $this->acceptanceCategory('license')->id]);
        LicenseSeat::factory()->create([
            'license_id' => $license->id,
            'asset_id' => $laptop->id,
            'assigned_to' => null,
        ]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutputToContain($holder->present()->fullName)
            ->assertExitCode(0);
    }

    public function test_license_seat_carrying_both_columns_yields_one_pair(): void
    {
        $holder = User::factory()->create();
        $carrier = Category::factory()->create(['category_type' => 'asset', 'require_acceptance' => false]);
        $laptop = $this->assetIn($carrier, [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);
        $license = License::factory()->create(['category_id' => $this->acceptanceCategory('license')->id]);
        LicenseSeat::factory()->create([
            'license_id' => $license->id,
            'asset_id' => $laptop->id,
            'assigned_to' => $holder->id,
        ]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutputToContain('To re-request: 1.')
            ->assertExitCode(0);
    }

    public function test_license_seat_keys_on_the_assets_current_holder_not_a_stale_assigned_to(): void
    {
        $currentHolder = User::factory()->create();
        $staleHolder = User::factory()->create();
        $laptop = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $currentHolder->id,
            'assigned_type' => User::class,
        ]);
        $license = License::factory()->create(['category_id' => $this->acceptanceCategory('license')->id]);
        LicenseSeat::factory()->create([
            'license_id' => $license->id,
            'asset_id' => $laptop->id,
            'assigned_to' => $staleHolder->id,
        ]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutputToContain($currentHolder->present()->fullName)
            ->doesntExpectOutputToContain($staleHolder->present()->fullName)
            ->assertExitCode(0);
    }

    public function test_license_seat_assigned_directly_to_a_user_is_a_candidate(): void
    {
        $holder = User::factory()->create();
        $license = License::factory()->create(['category_id' => $this->acceptanceCategory('license')->id]);
        LicenseSeat::factory()->create([
            'license_id' => $license->id,
            'asset_id' => null,
            'assigned_to' => $holder->id,
        ]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutputToContain($holder->present()->fullName)
            ->assertExitCode(0);
    }

    public function test_accessory_units_are_counted_per_pivot_row(): void
    {
        $holder = User::factory()->create();
        $accessory = Accessory::factory()->create(['category_id' => $this->acceptanceCategory('accessory')->id]);
        $accessory->checkouts()->createMany([
            ['assigned_to' => $holder->id, 'assigned_type' => User::class],
            ['assigned_to' => $holder->id, 'assigned_type' => User::class],
        ]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsTable(
                ['User', 'Item', 'Type', 'Units held', 'Qty'],
                [[$holder->present()->fullName, $accessory->present()->name, 'Accessory', 2, 2]],
            )
            ->assertExitCode(0);
    }

    public function test_accessory_checked_out_to_an_asset_resolves_to_that_assets_holder(): void
    {
        $holder = User::factory()->create();
        $laptop = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);
        $accessory = Accessory::factory()->create(['category_id' => $this->acceptanceCategory('accessory')->id]);
        $accessory->checkouts()->create(['assigned_to' => $laptop->id, 'assigned_type' => Asset::class]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutputToContain($accessory->present()->name)
            ->assertExitCode(0);
    }

    public function test_accessory_checked_out_to_a_location_is_not_a_candidate(): void
    {
        $accessory = Accessory::factory()->create(['category_id' => $this->acceptanceCategory('accessory')->id]);
        $accessory->checkouts()->create([
            'assigned_to' => Location::factory()->create()->id,
            'assigned_type' => Location::class,
        ]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutput('No users currently hold items requiring acceptance in that scope.')
            ->assertExitCode(0);
    }

    public function test_consumable_units_are_counted_per_pivot_row(): void
    {
        $holder = User::factory()->create();
        $consumable = Consumable::factory()->create(['category_id' => $this->acceptanceCategory('consumable')->id]);
        $consumable->users()->attach([$holder->id, $holder->id], ['created_by' => $holder->id]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsTable(
                ['User', 'Item', 'Type', 'Units held', 'Qty'],
                [[$holder->present()->fullName, $consumable->present()->name, 'Consumable', 2, 2]],
            )
            ->assertExitCode(0);
    }

    public function test_component_units_sum_assigned_qty_across_the_users_assets(): void
    {
        $holder = User::factory()->create();
        $category = $this->acceptanceCategory('asset');
        $laptop = $this->assetIn($category, ['assigned_to' => $holder->id, 'assigned_type' => User::class]);
        $desktop = $this->assetIn($category, ['assigned_to' => $holder->id, 'assigned_type' => User::class]);

        $component = Component::factory()->create(['category_id' => $this->acceptanceCategory('component')->id]);
        $component->assets()->attach($laptop->id, ['assigned_qty' => 3, 'created_by' => $holder->id]);
        $component->assets()->attach($desktop->id, ['assigned_qty' => 3, 'created_by' => $holder->id]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsTable(
                ['User', 'Item', 'Type', 'Units held', 'Qty'],
                [
                    [$holder->present()->fullName, $laptop->present()->name, 'Asset', 1, 1],
                    [$holder->present()->fullName, $desktop->present()->name, 'Asset', 1, 1],
                    [$holder->present()->fullName, $component->present()->name, 'Component', 6, 6],
                ],
            )
            ->assertExitCode(0);
    }

    public function test_component_on_an_unassigned_asset_is_not_a_candidate(): void
    {
        $parked = $this->assetIn($this->acceptanceCategory('asset'));
        $component = Component::factory()->create(['category_id' => $this->acceptanceCategory('component')->id]);
        $component->assets()->attach($parked->id, ['assigned_qty' => 2, 'created_by' => 1]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutput('No users currently hold items requiring acceptance in that scope.')
            ->assertExitCode(0);
    }

    public function test_category_filter_limits_the_candidate_set(): void
    {
        $holder = User::factory()->create();
        $wanted = $this->acceptanceCategory('asset');
        $unwanted = $this->acceptanceCategory('asset');

        $this->assetIn($wanted, ['assigned_to' => $holder->id, 'assigned_type' => User::class]);
        $this->assetIn($unwanted, ['assigned_to' => $holder->id, 'assigned_type' => User::class]);

        $this->artisan('snipeit:regenerate-acceptances', ['--category' => [$wanted->id]])
            ->expectsOutputToContain('To re-request: 1.')
            ->assertExitCode(0);
    }

    public function test_company_filter_reaches_license_seats_through_the_license(): void
    {
        $holder = User::factory()->create();
        $wanted = Company::factory()->create();
        $unwanted = Company::factory()->create();

        $category = $this->acceptanceCategory('license');

        foreach ([$wanted, $unwanted] as $company) {
            $license = License::factory()->create([
                'category_id' => $category->id,
                'company_id' => $company->id,
            ]);
            LicenseSeat::factory()->create([
                'license_id' => $license->id,
                'asset_id' => null,
                'assigned_to' => $holder->id,
            ]);
        }

        $this->artisan('snipeit:regenerate-acceptances', ['--company' => [$wanted->id]])
            ->expectsOutputToContain('To re-request: 1.')
            ->assertExitCode(0);
    }

    public function test_pending_acceptance_covering_the_units_held_is_skipped(): void
    {
        $holder = User::factory()->create();
        $asset = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);
        $this->acceptanceFor($asset, $holder)->pending()->create();

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutput('To re-request: 0.')
            ->expectsOutput('Already covered by a pending request: 1.')
            ->assertExitCode(0);
    }

    public function test_pair_that_already_accepted_is_re_requested(): void
    {
        $holder = User::factory()->create();
        $asset = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);
        $this->acceptanceFor($asset, $holder)->accepted()->create();

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutput('To re-request: 1.')
            ->expectsOutput('Already covered by a pending request: 0.')
            ->assertExitCode(0);
    }

    public function test_trashed_acceptance_does_not_count_toward_pending_coverage(): void
    {
        $holder = User::factory()->create();
        $asset = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);
        $this->acceptanceFor($asset, $holder)->pending()->create()->delete();

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutput('To re-request: 1.')
            ->assertExitCode(0);
    }

    public function test_partially_covered_accessory_re_requests_only_the_shortfall(): void
    {
        $holder = User::factory()->create();
        $accessory = Accessory::factory()->create(['category_id' => $this->acceptanceCategory('accessory')->id]);
        $accessory->checkouts()->createMany([
            ['assigned_to' => $holder->id, 'assigned_type' => User::class],
            ['assigned_to' => $holder->id, 'assigned_type' => User::class],
            ['assigned_to' => $holder->id, 'assigned_type' => User::class],
        ]);
        $pending = $this->acceptanceFor($accessory, $holder)->pending()->create(['qty' => 1]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsTable(
                ['User', 'Item', 'Type', 'Units held', 'Qty'],
                [[$holder->present()->fullName, $accessory->present()->name, 'Accessory', 3, 2]],
            )
            ->assertExitCode(0);

        $this->assertDatabaseHas('checkout_acceptances', [
            'id' => $pending->id,
            'qty' => 1,
            'accepted_at' => null,
            'declined_at' => null,
            'deleted_at' => null,
        ]);
        $this->assertSame(1, CheckoutAcceptance::count());
    }

    public function test_pending_acceptance_with_a_null_qty_covers_one_unit(): void
    {
        $holder = User::factory()->create();
        $accessory = Accessory::factory()->create(['category_id' => $this->acceptanceCategory('accessory')->id]);
        $accessory->checkouts()->createMany([
            ['assigned_to' => $holder->id, 'assigned_type' => User::class],
            ['assigned_to' => $holder->id, 'assigned_type' => User::class],
        ]);
        $this->acceptanceFor($accessory, $holder)->pending()->create(['qty' => null]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsTable(
                ['User', 'Item', 'Type', 'Units held', 'Qty'],
                [[$holder->present()->fullName, $accessory->present()->name, 'Accessory', 2, 1]],
            )
            ->assertExitCode(0);
    }

    /**
     * `acceptanceHistory()` deliberately over-fetches — it queries every candidate item id
     * against every candidate user id, a cross product — so rows belonging to other
     * holders of the same accessory arrive in the same result set and have to be split
     * apart in PHP. The other pair's row is worth 5 against this holder's single unit, so
     * a keying mistake flips this pair to "already covered" instead of shaving a unit off.
     */
    public function test_another_pairs_pending_acceptance_does_not_count_toward_this_pairs_coverage(): void
    {
        $holder = User::factory()->create();
        $accessory = Accessory::factory()->create(['category_id' => $this->acceptanceCategory('accessory')->id]);
        $accessory->checkouts()->create(['assigned_to' => $holder->id, 'assigned_type' => User::class]);
        $this->acceptanceFor($accessory, User::factory()->create())->pending()->create(['qty' => 5]);

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsTable(
                ['User', 'Item', 'Type', 'Units held', 'Qty'],
                [[$holder->present()->fullName, $accessory->present()->name, 'Accessory', 1, 1]],
            )
            ->assertExitCode(0);
    }

    public function test_pair_that_declined_is_re_requested_by_default(): void
    {
        $holder = User::factory()->create();
        $asset = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);
        $this->acceptanceFor($asset, $holder)->declined()->create();

        $this->artisan('snipeit:regenerate-acceptances')
            ->expectsOutput('To re-request: 1.')
            ->expectsOutput('Previously declined: 1.')
            ->assertExitCode(0);
    }

    public function test_pair_that_declined_is_excluded_and_counted_under_exclude_declined(): void
    {
        $holder = User::factory()->create();
        $asset = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);
        $this->acceptanceFor($asset, $holder)->declined()->create();

        $this->artisan('snipeit:regenerate-acceptances', ['--exclude-declined' => true])
            ->expectsOutput('To re-request: 0.')
            ->expectsOutput('Previously declined: 1.')
            ->expectsOutput('Previously declined and excluded: 1.')
            ->assertExitCode(0);
    }

    public function test_a_decline_answered_again_later_is_not_treated_as_declined(): void
    {
        $holder = User::factory()->create();
        $asset = $this->assetIn($this->acceptanceCategory('asset'), [
            'assigned_to' => $holder->id,
            'assigned_type' => User::class,
        ]);
        $this->acceptanceFor($asset, $holder)->declined()->create();
        $this->acceptanceFor($asset, $holder)->accepted()->create();

        $this->artisan('snipeit:regenerate-acceptances', ['--exclude-declined' => true])
            ->expectsOutput('To re-request: 1.')
            ->expectsOutput('Previously declined: 0.')
            ->assertExitCode(0);
    }

    /**
     * A CheckoutAcceptance factory already keyed to one (item, user) pair. The action
     * log the factory would otherwise write is irrelevant to classification, so it is
     * turned off rather than left to stamp the asset's assignment a second time.
     */
    private function acceptanceFor(Model $item, User $user): CheckoutAcceptanceFactory
    {
        return CheckoutAcceptance::factory()->withoutActionLog()->state([
            'checkoutable_type' => $item->getMorphClass(),
            'checkoutable_id' => $item->getKey(),
            'assigned_to_id' => $user->id,
        ]);
    }

    private function acceptanceCategory(string $type): Category
    {
        return Category::factory()->create([
            'category_type' => $type,
            'require_acceptance' => true,
        ]);
    }

    private function assetIn(Category $category, array $attributes = []): Asset
    {
        return Asset::factory()->create(array_merge([
            'model_id' => AssetModel::factory()->create(['category_id' => $category->id]),
        ], $attributes));
    }
}
