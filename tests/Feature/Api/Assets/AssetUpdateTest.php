<?php

namespace Tests\Feature\Api\Assets;

use App\Models\Asset;
use App\Models\User;
use Tests\Support\InteractsWithSettings;
use Tests\TestCase;

class AssetUpdateTest extends TestCase
{
    use InteractsWithSettings;

    public function testRequiresPermissionToUpdateAsset()
    {
        $this->actingAsForApi(User::factory()->create())
            ->patchJson(route('api.assets.update', Asset::factory()->create()))
            ->assertForbidden();
    }

    public function testReturnsErrorMessageIfAssetDoesNotExist()
    {
        $this->actingAsForApi(User::factory()->editAssets()->create())
            ->patchJson(route('api.assets.update', 1000))
            ->assertStatusMessageIs('error');
    }

    public function testCanUpdateAssetViaPut()
    {
        $this->markTestIncomplete();
    }

    public function testCanUpdateAssetViaPatch()
    {
        $this->markTestIncomplete();
    }
}
