<?php

namespace Tests\Feature\AssetModels\Api;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteAssetModelsTest extends TestCase implements TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $assetModel = AssetModel::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->deleteJson(route('api.models.destroy', $assetModel))
            ->assertForbidden();

        $this->assertNotSoftDeleted($assetModel);
    }

    public function testCannotDeleteAssetModelThatStillHasAssociatedAssets()
    {
        $assetModel = Asset::factory()->create()->model;

        $this->actingAsForApi(User::factory()->deleteAssetModels()->create())
            ->deleteJson(route('api.models.destroy', $assetModel))
            ->assertStatusMessageIs('error');

        $this->assertNotSoftDeleted($assetModel);
    }

    public function testCanDeleteAssetModel()
    {
        $assetModel = AssetModel::factory()->create();

        $this->actingAsForApi(User::factory()->deleteAssetModels()->create())
            ->deleteJson(route('api.models.destroy', $assetModel))
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted($assetModel);
    }

    public function test_preserves_image_in_case_asset_model_restored()
    {
        Storage::fake('public');

        $filepath = 'models/temp-file.jpg';

        Storage::disk('public')->put($filepath, 'contents');

        $assetModel = AssetModel::factory()->create(['image' => 'temp-file.jpg']);

        $this->actingAsForApi(User::factory()->deleteAssetModels()->create())
            ->deleteJson(route('api.models.destroy', $assetModel))
            ->assertStatusMessageIs('success');

        Storage::disk('public')->assertExists($filepath);
    }

    public function test_preserves_uploads_in_case_asset_model_restored()
    {
        Storage::fake('public');

        $filepath = 'private_uploads/models';

        $assetModel = AssetModel::factory()->create();

        Storage::put("{$filepath}/to-keep.txt", 'contents');
        $assetModel->logUpload("to-keep.txt", '');

        $this->actingAsForApi(User::factory()->deleteAssetModels()->create())
            ->deleteJson(route('api.models.destroy', $assetModel))
            ->assertStatusMessageIs('success');

        Storage::assertExists("{$filepath}/to-keep.txt");
    }
}
