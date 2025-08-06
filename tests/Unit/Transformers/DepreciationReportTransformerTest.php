<?php

use App\Http\Transformers\DepreciationReportTransformer;
use App\Models\Asset;
use App\Models\Depreciation;

test('handles model depreciation months being zero', function () {
    $asset = Asset::factory()->create();
    $depreciation = Depreciation::factory()->create(['months' => 0]);
    $asset->model->depreciation()->associate($depreciation);

    $transformer = new DepreciationReportTransformer;

    $result = $transformer->transformAsset($asset);

    expect($result)->toBeArray();
});
