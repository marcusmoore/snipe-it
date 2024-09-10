<?php

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
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

test('auto increment b i g', function () {
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

test('auto increment almost b i g', function () {
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
