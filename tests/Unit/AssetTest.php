<?php

use App\Http\Controllers\Assets\BulkAssetsController;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Statuslabel;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Setting;


test('auto increment', function () {
    $this->settings->enableAutoIncrement();

    $a = Asset::factory()->create(['asset_tag' => Asset::autoincrement_asset() ]);
    $b = Asset::factory()->create(['asset_tag' => Asset::autoincrement_asset() ]);

    $this->assertModelExists($a);
    $this->assertModelExists($b);
});

test('auto increment collision', function () {
    $this->settings->enableAutoIncrement();

    // we have to do this by hand to 'simulate' two web pages being open at the same time
    $a = Asset::factory()->make(['asset_tag' => Asset::autoincrement_asset() ]);
    $b = Asset::factory()->make(['asset_tag' => Asset::autoincrement_asset() ]);

    expect($a->save())->toBeTrue();
    expect($b->save())->toBeFalse();
});

test('auto increment double', function () {
    // make one asset with the autoincrement *ONE* higher than the next auto-increment
    // make sure you can then still make another
    $this->settings->enableAutoIncrement();

    $gap_number = Asset::autoincrement_asset(1);
    $final_number = Asset::autoincrement_asset(2);
    $a = Asset::factory()->make(['asset_tag' => $gap_number]);
    //make an asset with an ID that is one *over* the next increment
    $b = Asset::factory()->make(['asset_tag' => Asset::autoincrement_asset()]);
    //but also make one with one that is *at* the next increment
    expect($a->save())->toBeTrue();
    expect($b->save())->toBeTrue();

    //and ensure a final asset ends up at *two* over what would've been the next increment at the start
    $c = Asset::factory()->make(['asset_tag' => Asset::autoincrement_asset()]);
    expect($c->save())->toBeTrue();
    expect($final_number)->toEqual($c->asset_tag);
});

test('auto increment gap and backfill', function () {
    // make one asset 3 higher than the next auto-increment
    // manually make one that's 1 lower than that
    // make sure the next one is one higher than the 3 higher one.
    $this->settings->enableAutoIncrement();

    $big_gap = Asset::autoincrement_asset(3);
    $final_result = Asset::autoincrement_asset(4);
    $backfill_one = Asset::autoincrement_asset(0);
    $backfill_two = Asset::autoincrement_asset(1);
    $backfill_three = Asset::autoincrement_asset(2);
    $a = Asset::factory()->create(['asset_tag' => $big_gap]);
    $this->assertModelExists($a);

    $b = Asset::factory()->create(['asset_tag' => $backfill_one]);
    $this->assertModelExists($b);

    $c = Asset::factory()->create(['asset_tag' => $backfill_two]);
    $this->assertModelExists($c);

    $d = Asset::factory()->create(['asset_tag' => $backfill_three]);
    $this->assertModelExists($d);

    $final = Asset::factory()->create(['asset_tag' => Asset::autoincrement_asset()]);
    $this->assertModelExists($final);
    expect($final_result)->toEqual($final->asset_tag);
});

test('prefixless autoincrement backfill', function () {
    // TODO: COPYPASTA FROM above, is there a way to still run this test but not have it be so duplicative?
    $this->settings->enableAutoIncrement()->set(['auto_increment_prefix' => '']);

    $big_gap = Asset::autoincrement_asset(3);
    $final_result = Asset::autoincrement_asset(4);
    $backfill_one = Asset::autoincrement_asset(0);
    $backfill_two = Asset::autoincrement_asset(1);
    $backfill_three = Asset::autoincrement_asset(2);
    $a = Asset::factory()->create(['asset_tag' => $big_gap]);
    $this->assertModelExists($a);

    $b = Asset::factory()->create(['asset_tag' => $backfill_one]);
    $this->assertModelExists($b);

    $c = Asset::factory()->create(['asset_tag' => $backfill_two]);
    $this->assertModelExists($c);

    $d = Asset::factory()->create(['asset_tag' => $backfill_three]);
    $this->assertModelExists($d);

    $final = Asset::factory()->create(['asset_tag' => Asset::autoincrement_asset()]);
    $this->assertModelExists($final);
    expect($final_result)->toEqual($final->asset_tag);
});

test('unzerofilled prefixless autoincrement backfill', function () {
    // TODO: COPYPASTA FROM above (AGAIN), is there a way to still run this test but not have it be so duplicative?
    $this->settings->enableAutoIncrement()->set(['auto_increment_prefix' => '','zerofill_count' => 0]);

    $big_gap = Asset::autoincrement_asset(3);
    $final_result = Asset::autoincrement_asset(4);
    $backfill_one = Asset::autoincrement_asset(0);
    $backfill_two = Asset::autoincrement_asset(1);
    $backfill_three = Asset::autoincrement_asset(2);
    $a = Asset::factory()->create(['asset_tag' => $big_gap]);
    $this->assertModelExists($a);

    $b = Asset::factory()->create(['asset_tag' => $backfill_one]);
    $this->assertModelExists($b);

    $c = Asset::factory()->create(['asset_tag' => $backfill_two]);
    $this->assertModelExists($c);

    $d = Asset::factory()->create(['asset_tag' => $backfill_three]);
    $this->assertModelExists($d);

    $final = Asset::factory()->create(['asset_tag' => Asset::autoincrement_asset()]);
    $this->assertModelExists($final);
    expect($final_result)->toEqual($final->asset_tag);
});

test('auto increment big', function () {
    $this->settings->enableAutoIncrement();

    // we have to do this by hand to 'simulate' two web pages being open at the same time
    $a = Asset::factory()->make(['asset_tag' => Asset::autoincrement_asset()]);
    $b = Asset::factory()->make(['asset_tag' => 'ABCD' . (PHP_INT_MAX - 1)]);

    expect($a->save())->toBeTrue();
    expect($b->save())->toBeTrue();
    $matches = [];
    preg_match('/\d+/', $a->asset_tag, $matches);
    expect($matches[0] + 1)->toEqual(Setting::getSettings()->next_auto_tag_base, "Next auto increment number should be the last normally-saved one plus one, but isn't");
});

test('auto increment almost big', function () {
    // TODO: this looks pretty close to the one above, could we maybe squish them together?
    $this->settings->enableAutoIncrement();

    // we have to do this by hand to 'simulate' two web pages being open at the same time
    $a = Asset::factory()->make(['asset_tag' => Asset::autoincrement_asset()]);
    $b = Asset::factory()->make(['asset_tag' => 'ABCD' . (PHP_INT_MAX - 2)]);

    expect($a->save())->toBeTrue();
    expect($b->save())->toBeTrue();
    $matches = [];
    preg_match('/\d+/', $b->asset_tag, $matches);
    //this is *b*, not *a* - slight difference from above test
    expect($matches[0] + 1)->toEqual(Setting::getSettings()->next_auto_tag_base, "Next auto increment number should be the last normally-saved one plus one, but isn't");
});

test('warranty expires attribute', function () {
    $asset = Asset::factory()
    ->create(
        [
            'model_id' => AssetModel::factory()
                ->create(
                    [
                        'category_id' => Category::factory()->assetLaptopCategory()->create()->id
                    ]
            )->id,   
            'warranty_months' => 24,
            'purchase_date' =>   Carbon::createFromDate(2017, 1, 1)->hour(0)->minute(0)->second(0)                  
        ]);

    expect($asset->purchase_date->format('Y-m-d'))->toEqual(Carbon::createFromDate(2017, 1, 1)->format('Y-m-d'));
    expect($asset->warranty_expires->format('Y-m-d'))->toEqual(Carbon::createFromDate(2019, 1, 1)->format('Y-m-d'));
});

test('assigned type without assign to', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create([
        'assigned_to' => $user->id
    ]);
    $this->assertModelMissing($asset);
});

test('get image url method', function () {
    $urlBase = config('filesystems.disks.public.url');

    $category = Category::factory()->create(['image' => 'category-image.jpg']);
    $model = AssetModel::factory()->for($category)->create(['image' => 'asset-model-image.jpg']);
    $asset = Asset::factory()->for($model, 'model')->create(['image' => 'asset-image.jpg']);

    expect($asset->getImageUrl())->toEqual("{$urlBase}/assets/asset-image.jpg");

    $asset->update(['image' => null]);

    expect($asset->refresh()->getImageUrl())->toEqual("{$urlBase}/models/asset-model-image.jpg");

    $model->update(['image' => null]);

    expect($asset->refresh()->getImageUrl())->toEqual("{$urlBase}/categories/category-image.jpg");

    $category->image = null;
    $category->save();

    expect($asset->refresh()->getImageUrl())->toBeFalse();

    // handles case where model does not exist
    $asset->model_id = 9999999;
    $asset->forceSave();

    expect($asset->refresh()->getImageUrl())->toBeFalse();
});

test('undeployable status returns falseif asset is deployable', function () {
    $assets = Asset::factory()->count(3)->create();
    $asset_ids = $assets->pluck('id')->toArray();

    $bulk_assets = new BulkAssetsController();

    $result = $bulk_assets->hasUndeployableStatus($asset_ids);

    expect($result)->toBeFalse();
});

test('undeployable status returns trueand tags if asset is un deployable', function () {
    $deployable = Asset::factory()->create();
    $undeployableStatus = Statuslabel::factory()->create(['deployable' => 0]);
    $undeployable = Asset::factory()->create(
        [
            'status_id' => $undeployableStatus->id
        ]);

    $bulk_assets = new BulkAssetsController();

    $result = $bulk_assets->hasUndeployableStatus([$deployable->id, $undeployable->id]);

    expect($result)->toBeArray();
    expect($result['status'])->toBeTrue();
    expect($result['tags'][0]['id'])->toEqual($undeployable->id);
    expect($result['tags'][0]['asset_tag'])->toEqual($undeployable->asset_tag);
});

test('undeployable status check filters out undeployable ids', function () {
    $deployable = Asset::factory()->create();
    $undeployableStatus = Statuslabel::factory()->create(['deployable' => 0]);
    $undeployable = Asset::factory()->create(
        [
            'status_id' => $undeployableStatus->id
        ]);

    $bulk_assets = new BulkAssetsController();

    $result = $bulk_assets->hasUndeployableStatus([$deployable->id, $undeployable->id]);

    $undeployableIds = array_column($result['tags'], 'id');
    $filtered = array_diff([$deployable->id, $undeployable->id], $undeployableIds);

    expect(array_values($filtered))->toEqual([$deployable->id]);
});
