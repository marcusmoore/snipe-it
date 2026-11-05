<?php

namespace Tests\Unit\Transformers;

use App\Http\Transformers\DepreciationReportTransformer;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Depreciation;
use Tests\TestCase;

class DepreciationReportTransformerTest extends TestCase
{
    public function test_handles_model_depreciation_months_being_zero()
    {
        $asset = Asset::factory()->create();
        $depreciation = Depreciation::factory()->create(['months' => 0]);
        $asset->model->depreciation()->associate($depreciation);

        $transformer = new DepreciationReportTransformer;

        $result = $transformer->transformAsset($asset);

        $this->assertIsArray($result);
    }

    public function test_eol_is_purchase_date_plus_model_months_for_calculated_asset()
    {
        $asset = $this->assetWithEol(AssetModel::factory()->create(['eol' => 36]), '2021-01-15', '2024-01-15', false);

        $result = (new DepreciationReportTransformer)->transformAsset($asset);

        $this->assertSame('2024-01-15', $result['eol']['date']);
    }

    /**
     * TODO: correct behavior is the asset's own explicit EOL date (2028-11-30
     * here); today the column ignores it and shows purchase date plus the
     * model's EOL months.
     */
    public function test_eol_for_explicit_asset_ignores_its_own_date_and_shows_purchase_date_plus_model_months()
    {
        $asset = $this->assetWithEol(AssetModel::factory()->create(['eol' => 36]), '2021-01-15', '2028-11-30', true);

        $result = (new DepreciationReportTransformer)->transformAsset($asset);

        $this->assertSame('2024-01-15', $result['eol']['date']);
    }

    /**
     * TODO: correct behavior is the asset's own explicit EOL date (2028-11-30
     * here); today the column is empty whenever the asset has no purchase date.
     */
    public function test_eol_is_null_for_explicit_asset_without_purchase_date()
    {
        $asset = $this->assetWithEol(AssetModel::factory()->create(['eol' => 36]), null, '2028-11-30', true);

        $result = (new DepreciationReportTransformer)->transformAsset($asset);

        $this->assertNull($result['eol']);
    }

    public function test_eol_is_null_when_model_has_no_eol_months()
    {
        $asset = $this->assetWithEol(AssetModel::factory()->create(['eol' => 0]), '2021-01-15', null, false);

        $result = (new DepreciationReportTransformer)->transformAsset($asset);

        $this->assertNull($result['eol']);
    }

    private function assetWithEol(AssetModel $model, ?string $purchaseDate, ?string $eolDate, bool $explicit): Asset
    {
        $asset = Asset::factory()->create(['model_id' => $model->id]);

        $asset->forceFill([
            'purchase_date' => $purchaseDate,
            'asset_eol_date' => $eolDate,
            'eol_explicit' => $explicit,
        ])->saveQuietly();

        return $asset->refresh();
    }
}
