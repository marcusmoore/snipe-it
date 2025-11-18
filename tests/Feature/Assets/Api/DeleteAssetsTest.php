<?php

namespace Tests\Feature\Assets\Api;

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\TestsFullMultipleCompaniesSupport;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteAssetsTest extends TestCase implements TestsFullMultipleCompaniesSupport, TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $asset = Asset::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->deleteJson(route('api.assets.destroy', $asset))
            ->assertForbidden();

        $this->assertNotSoftDeleted($asset);
    }

    public function testAdheresToFullMultipleCompaniesSupportScoping()
    {
        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $assetA = Asset::factory()->for($companyA)->create();
        $assetB = Asset::factory()->for($companyB)->create();
        $assetC = Asset::factory()->for($companyB)->create();

        $superUser = $companyA->users()->save(User::factory()->superuser()->make());
        $userInCompanyA = $companyA->users()->save(User::factory()->deleteAssets()->make());
        $userInCompanyB = $companyB->users()->save(User::factory()->deleteAssets()->make());

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($userInCompanyA)
            ->deleteJson(route('api.assets.destroy', $assetB))
            ->assertStatusMessageIs('error');

        $this->actingAsForApi($userInCompanyB)
            ->deleteJson(route('api.assets.destroy', $assetA))
            ->assertStatusMessageIs('error');

        $this->actingAsForApi($superUser)
            ->deleteJson(route('api.assets.destroy', $assetC))
            ->assertStatusMessageIs('success');

        $this->assertNotSoftDeleted($assetA);
        $this->assertNotSoftDeleted($assetB);
        $this->assertSoftDeleted($assetC);
    }

    public function testCannotDeleteAssetThatIsCheckedOut()
    {
        $this->markTestSkipped('This behavior is not functioning yet.');
    }

    public function testCanDeleteAsset()
    {
        $asset = Asset::factory()->create();

        $this->actingAsForApi(User::factory()->deleteAssets()->create())
            ->deleteJson(route('api.assets.destroy', $asset))
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted($asset);
        $this->assertHasTheseActionLogs($asset, ['create', 'delete']);
    }

    public function test_preserves_image_in_case_asset_restored()
    {
        Storage::fake('public');

        $asset = Asset::factory()->create(['image' => 'image.jpg']);

        Storage::disk('public')->put('assets/image.jpg', 'content');

        Storage::disk('public')->assertExists('assets/image.jpg');

        $this->actingAsForApi(User::factory()->deleteAssets()->create())
            ->deleteJson(route('api.assets.destroy', $asset))
            ->assertStatusMessageIs('success');

        Storage::disk('public')->assertExists('assets/image.jpg');
    }

    public function test_preserves_uploads_in_case_asset_restored()
    {
        $this->markTestIncomplete();
    }
}
