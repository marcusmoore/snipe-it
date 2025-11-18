<?php

namespace Tests\Feature\Console\Purge;

use App\Models\AssetModel;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeAssetModelTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_asset_models_purged()
    {
        $assetModels = AssetModel::factory()->count(2)->create();

        $assetModels->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('models', ['id' => $assetModels->first()->id]);
        $this->assertDatabaseHas('models', ['id' => $assetModels->last()->id]);
    }

    public function test_deletes_asset_models_image()
    {
        $filename = str_random() . '.jpg';

        $assetModel = AssetModel::factory()->create(['image' => $filename]);

        $filepath = "models/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $assetModel->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public function test_deletes_asset_model_uploads()
    {
        $this->markTestIncomplete();
    }
}
