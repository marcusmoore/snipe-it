<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Asset;
use App\Models\Maintenance;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeAssetTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_assets_purged()
    {
        $assets = Asset::factory()->count(2)->create();

        $assets->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('assets', ['id' => $assets->first()->id]);
        $this->assertDatabaseHas('assets', ['id' => $assets->last()->id]);
    }

    public function test_purges_maintenances_for_soft_deleted_assets()
    {
        // create maintenance
        $maintenance = Maintenance::factory()->create();

        // delete its asset
        $maintenance->asset->delete();

        // fire command
        $this->firePurgeCommand()->assertSuccessful();

        // ensure maintenance completely removed
        $this->assertDatabaseMissing($maintenance->getTable(), ['id' => $maintenance->id]);
    }

    public function test_associated_action_logs_are_not_purged_by_default()
    {
        $this->markTestIncomplete();
    }

    public function test_associated_action_logs_can_be_purged_via_env_variable()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_assets_image()
    {
        $filename = str_random() . '.jpg';

        $asset = Asset::factory()->create(['image' => $filename]);

        $filepath = "assets/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $asset->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public function test_deletes_asset_uploads()
    {
        $filepath = 'private_uploads/assets';

        $assets = Asset::factory()->count(2)->create();

        Storage::put("{$filepath}/to-remove.txt", 'contents');
        $assets->first()->logUpload("to-remove.txt", '');

        Storage::put("{$filepath}/to-keep.txt", 'contents');
        $assets->last()->logUpload("to-keep.txt", '');

        $assets->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        Storage::assertMissing("{$filepath}/to-remove.txt");
        Storage::assertExists("{$filepath}/to-keep.txt");
    }
}
