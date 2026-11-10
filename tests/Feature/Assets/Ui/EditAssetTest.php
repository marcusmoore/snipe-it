<?php

namespace Tests\Feature\Assets\Ui;

use App\Events\CheckoutableCheckedIn;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EditAssetTest extends TestCase
{
    public function test_permission_required_to_view_edit_asset_page()
    {
        $asset = Asset::factory()->create();
        $this->actingAs(User::factory()->create())
            ->get(route('hardware.edit', $asset))
            ->assertForbidden();
    }

    public function test_page_can_be_accessed(): void
    {
        $asset = Asset::factory()->create();
        $user = User::factory()->editAssets()->create();
        $response = $this->actingAs($user)->get(route('hardware.edit', $asset));
        $response->assertStatus(200);
    }

    public function test_asset_edit_post_is_redirected_if_redirect_selection_is_index()
    {
        $asset = Asset::factory()->assignedToUser()->create();

        $this->actingAs(User::factory()->viewAssets()->editAssets()->create())
            ->from(route('hardware.edit', $asset))
            ->put(route('hardware.update', $asset),
                [
                    'redirect_option' => 'index',
                    'name' => 'New name',
                    'asset_tags' => 'New Asset Tag',
                    'status_id' => StatusLabel::factory()->create()->id,
                    'model_id' => AssetModel::factory()->create()->id,
                ])
            ->assertStatus(302)
            ->assertRedirect(route('hardware.index'));
        $this->assertDatabaseHas('assets', ['asset_tag' => 'New Asset Tag']);
    }

    public function test_asset_edit_post_is_redirected_if_redirect_selection_is_item()
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->viewAssets()->editAssets()->create())
            ->from(route('hardware.edit', $asset))
            ->put(route('hardware.update', $asset), [
                'redirect_option' => 'item',
                'name' => 'New name',
                'asset_tags' => 'New Asset Tag',
                'status_id' => StatusLabel::factory()->create()->id,
                'model_id' => AssetModel::factory()->create()->id,
            ])
            ->assertStatus(302)
            ->assertRedirect(route('hardware.show', $asset));

        $this->assertDatabaseHas('assets', ['asset_tag' => 'New Asset Tag']);
    }

    public function test_new_checkin_is_logged_if_status_changed_to_undeployable()
    {
        Event::fake([CheckoutableCheckedIn::class]);

        $user = User::factory()->create();
        $deployable_status = StatusLabel::factory()->rtd()->create();
        $achived_status = StatusLabel::factory()->archived()->create();
        $asset = Asset::factory()->assignedToUser($user)->create([
            'status_id' => $deployable_status->id,
            'last_checkin' => null,
        ]);
        $this->assertTrue($asset->assignedTo->is($user));

        $currentTimestamp = now();

        $this->actingAs(User::factory()->viewAssets()->editAssets()->create())
            ->from(route('hardware.edit', $asset))
            ->put(route('hardware.update', $asset), [
                'status_id' => $achived_status->id,
                'model_id' => $asset->model_id,
                'asset_tags' => $asset->asset_tag,
            ],
            )
            ->assertStatus(302);
        // ->assertRedirect(route('hardware.show', ['hardware' => $asset->id]));;

        // $asset->refresh();
        $asset = Asset::find($asset->id);
        $this->assertNull($asset->assigned_to);
        $this->assertNull($asset->assigned_type);
        $this->assertEquals($achived_status->id, $asset->status_id);
        $this->assertNotNull($asset->last_checkin);

        Event::assertDispatched(function (CheckoutableCheckedIn $event) use ($currentTimestamp) {
            return (int) Carbon::parse($event->action_date)->diffInSeconds($currentTimestamp, true) < 2;
        }, 1);
    }

    public function test_current_location_is_not_updated_on_edit()
    {
        $defaultLocation = Location::factory()->create();
        $currentLocation = Location::factory()->create();
        $asset = Asset::factory()->create([
            'location_id' => $currentLocation->id,
            'rtd_location_id' => $defaultLocation->id,
        ]);

        $this->actingAs(User::factory()->viewAssets()->editAssets()->create())
            ->put(route('hardware.update', $asset), [
                'redirect_option' => 'item',
                'name' => 'New name',
                'asset_tags' => 'New Asset Tag',
                'status_id' => $asset->status_id,
                'model_id' => $asset->model_id,
            ]);

        $asset->refresh();
        $this->assertEquals('New name', $asset->name);
        $this->assertEquals($currentLocation->id, $asset->location_id);
    }

    public function test_handles_model_being_deleted()
    {
        $this->withoutExceptionHandling();

        $newStatus = StatusLabel::factory()->create();

        $asset = Asset::factory()->create();

        $asset->model()->forceDelete();

        $this->actingAs(User::factory()->viewAssets()->editAssets()->create())
            ->from(route('hardware.edit', $asset))
            ->put(route('hardware.update', $asset), [
                'redirect_option' => 'index',
                'purchase_date' => '2025-08-30',
                'name' => 'New name',
                'asset_tags' => 'New Asset Tag',
                'status_id' => $newStatus->id,
                // triggers potential issue in AssetObserver's saving method
                'model_id' => AssetModel::factory()->create()->id,
            ]);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status_id' => $newStatus->id,
        ]);
    }

    public function test_changing_purchase_date_without_eol_date_recalculates_eol_date()
    {
        $model = AssetModel::factory()->create(['eol' => 36]);
        $asset = $this->assetWithEol($model, '2021-01-15', '2024-01-15', false);

        $this->updateAsset($asset, ['purchase_date' => '2022-03-15']);

        $asset->refresh();
        $this->assertSame('2025-03-15', $asset->asset_eol_date);
        $this->assertFalse($asset->eol_explicit);
    }

    public function test_changing_purchase_date_without_eol_date_replaces_an_explicit_eol_date()
    {
        $model = AssetModel::factory()->create(['eol' => 36]);
        $asset = $this->assetWithEol($model, '2021-01-15', '2028-11-30', true);

        $this->updateAsset($asset, ['purchase_date' => '2022-03-15']);

        $asset->refresh();
        $this->assertSame('2025-03-15', $asset->asset_eol_date);
        $this->assertFalse($asset->eol_explicit);
    }

    public function test_submitted_eol_date_that_differs_from_calculated_date_is_marked_explicit()
    {
        $model = AssetModel::factory()->create(['eol' => 36]);
        $asset = $this->assetWithEol($model, '2021-01-15', '2024-01-15', false);

        $this->updateAsset($asset, [
            'purchase_date' => '2021-01-15',
            'asset_eol_date' => '2028-11-30',
        ]);

        $asset->refresh();
        $this->assertSame('2028-11-30', $asset->asset_eol_date);
        $this->assertTrue($asset->eol_explicit);
    }

    public function test_submitted_eol_date_that_matches_calculated_date_is_not_marked_explicit()
    {
        $model = AssetModel::factory()->create(['eol' => 36]);
        $asset = $this->assetWithEol($model, '2021-01-15', '2028-11-30', true);

        $this->updateAsset($asset, [
            'purchase_date' => '2021-01-15',
            'asset_eol_date' => '2024-01-15',
        ]);

        $asset->refresh();
        $this->assertSame('2024-01-15', $asset->asset_eol_date);
        $this->assertFalse($asset->eol_explicit);
    }

    public function test_eol_date_is_cleared_when_model_has_no_eol_months_and_no_eol_date_is_submitted()
    {
        $model = AssetModel::factory()->create(['eol' => 0]);
        $asset = $this->assetWithEol($model, '2021-01-15', '2028-11-30', true);

        $this->updateAsset($asset, ['purchase_date' => '2021-01-15']);

        $asset->refresh();
        $this->assertNull($asset->asset_eol_date);
        $this->assertFalse($asset->eol_explicit);
    }

    public function test_submitted_eol_date_is_marked_explicit_when_model_has_no_eol_months()
    {
        $model = AssetModel::factory()->create(['eol' => 0]);
        $asset = $this->assetWithEol($model, '2021-01-15', null, false);

        $this->updateAsset($asset, [
            'purchase_date' => '2021-01-15',
            'asset_eol_date' => '2028-11-30',
        ]);

        $asset->refresh();
        $this->assertSame('2028-11-30', $asset->asset_eol_date);
        $this->assertTrue($asset->eol_explicit);
    }

    public function test_changing_model_recalculates_eol_date_from_the_new_model()
    {
        $oldModel = AssetModel::factory()->create(['eol' => 36]);
        $newModel = AssetModel::factory()->create(['eol' => 24]);
        $asset = $this->assetWithEol($oldModel, '2021-01-15', '2024-01-15', false);

        $this->updateAsset($asset, [
            'model_id' => $newModel->id,
            'purchase_date' => '2021-01-15',
        ]);

        $asset->refresh();
        $this->assertSame($newModel->id, $asset->model_id);
        $this->assertSame('2023-01-15', $asset->asset_eol_date);
        $this->assertFalse($asset->eol_explicit);
    }

    /**
     * The edit form pre-fills the EOL date, so this is what a normal model change
     * through the UI submits.
     */
    public function test_changing_model_with_the_prefilled_eol_date_follows_the_new_model()
    {
        $oldModel = AssetModel::factory()->create(['eol' => 36]);
        $newModel = AssetModel::factory()->create(['eol' => 24]);
        $asset = $this->assetWithEol($oldModel, '2021-01-15', '2024-01-15', false);

        $this->updateAsset($asset, [
            'model_id' => $newModel->id,
            'purchase_date' => '2021-01-15',
            'asset_eol_date' => '2024-01-15',
        ]);

        $asset->refresh();
        $this->assertSame($newModel->id, $asset->model_id);
        $this->assertSame('2023-01-15', $asset->asset_eol_date);
        $this->assertFalse($asset->eol_explicit);
    }

    private function assetWithEol(AssetModel $model, ?string $purchaseDate, ?string $eolDate, bool $explicit): Asset
    {
        $asset = Asset::factory()->create(['model_id' => $model->id]);

        $asset->forceFill([
            'purchase_date' => $purchaseDate,
            'asset_eol_date' => $eolDate,
            'eol_explicit' => $explicit,
        ])->saveQuietly();

        return $asset;
    }

    private function updateAsset(Asset $asset, array $attributes): void
    {
        $this->actingAs(User::factory()->viewAssets()->editAssets()->create())
            ->put(route('hardware.update', $asset), array_merge([
                'redirect_option' => 'item',
                'asset_tags' => $asset->asset_tag,
                'status_id' => $asset->status_id,
                'model_id' => $asset->model_id,
            ], $attributes))
            ->assertRedirect(route('hardware.show', $asset))
            ->assertSessionHasNoErrors();
    }
}
