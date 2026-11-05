<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Asset;
use App\Models\AssetModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RemoveExplicitEolsTest extends TestCase
{
    #[Test]
    public function named_model_has_its_explicit_eol_dates_recalculated_and_other_models_are_untouched()
    {
        $model = AssetModel::factory()->create(['eol' => 36]);
        $asset = $this->assetWithEol($model, '2021-01-15', '2028-11-30', true);
        $otherAsset = $this->assetWithEol(AssetModel::factory()->create(['eol' => 36]), '2021-01-15', '2028-11-30', true);

        $this->artisan('snipeit:remove-explicit-eols', ['--model_name' => $model->name])
            ->assertSuccessful();

        $asset->refresh();
        $this->assertSame('2024-01-15', $asset->asset_eol_date);
        $this->assertFalse($asset->eol_explicit);

        $otherAsset->refresh();
        $this->assertSame('2028-11-30', $otherAsset->asset_eol_date);
        $this->assertTrue($otherAsset->eol_explicit);
    }

    #[Test]
    public function all_option_recalculates_explicit_eol_dates_for_every_model()
    {
        $first = $this->assetWithEol(AssetModel::factory()->create(['eol' => 36]), '2021-01-15', '2028-11-30', true);
        $second = $this->assetWithEol(AssetModel::factory()->create(['eol' => 24]), '2021-01-15', '2028-11-30', true);

        $this->artisan('snipeit:remove-explicit-eols', ['--model_name' => 'all'])
            ->assertSuccessful();

        $first->refresh();
        $this->assertSame('2024-01-15', $first->asset_eol_date);
        $this->assertFalse($first->eol_explicit);

        $second->refresh();
        $this->assertSame('2023-01-15', $second->asset_eol_date);
        $this->assertFalse($second->eol_explicit);
    }

    #[Test]
    public function asset_without_purchase_date_is_left_with_no_eol_date()
    {
        $model = AssetModel::factory()->create(['eol' => 36]);
        $asset = $this->assetWithEol($model, null, '2028-11-30', true);

        $this->artisan('snipeit:remove-explicit-eols', ['--model_name' => $model->name])
            ->assertSuccessful();

        $asset->refresh();
        $this->assertNull($asset->asset_eol_date);
        $this->assertFalse($asset->eol_explicit);
    }

    #[Test]
    public function unknown_model_name_reports_an_error_and_changes_nothing()
    {
        $asset = $this->assetWithEol(AssetModel::factory()->create(['eol' => 36]), '2021-01-15', '2028-11-30', true);

        $this->artisan('snipeit:remove-explicit-eols', ['--model_name' => 'No Such Model'])
            ->expectsOutput('Asset model not found');

        $asset->refresh();
        $this->assertSame('2028-11-30', $asset->asset_eol_date);
        $this->assertTrue($asset->eol_explicit);
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
}
