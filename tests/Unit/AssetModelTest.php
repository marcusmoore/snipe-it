<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\AssetModel;

test('an asset model contains assets', function () {
    $category = Category::factory()->create([
        'category_type' => 'asset'
        ]);
    $model = AssetModel::factory()->create([
        'category_id' => $category->id,
    ]);

    $asset = Asset::factory()->create([
                'model_id' => $model->id
            ]);
    expect($model->assets()->count())->toEqual(1);
});
