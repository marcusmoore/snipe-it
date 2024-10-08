<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\Component;
use App\Models\LicenseSeat;
use App\Models\User;
use Tests\Concerns\TestsFullMultipleCompaniesSupport;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteAssetsTest extends TestCase implements TestsFullMultipleCompaniesSupport, TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('hardware.destroy', $asset))
            ->assertForbidden();

        $this->assertNotSoftDeleted($asset);
    }

    public function testAdheresToFullMultipleCompaniesSupportScoping()
    {
        $this->markTestIncomplete();
    }

    public function testCannotDeleteAssetThatHasAssetsCheckedOutToIt()
    {
        $computer = Asset::factory()->create();
        $fancyKeyboard = Asset::factory()->create();

        $fancyKeyboard->checkOut(
            $computer,
            User::factory()->create()
        );

        $this->actingAs(User::factory()->deleteAssets()->viewAssets()->create())
            ->delete(route('hardware.destroy', $computer));

        $this->assertNotSoftDeleted($computer);
    }

    public function testCannotDeleteAssetThatHasComponentsCheckedOutToIt()
    {
        $computer = Asset::factory()->create();
        Component::factory()->checkedOutToAsset($computer)->create();

        $this->actingAs(User::factory()->deleteAssets()->viewAssets()->create())
            ->delete(route('hardware.destroy', $computer));

        $this->assertNotSoftDeleted($computer);
    }

    public function testCannotDeleteAssetThatHasLicensesCheckedOutToIt()
    {
        $computer = Asset::factory()->create();
        LicenseSeat::factory()->assignedToAsset($computer)->create();

        $this->actingAs(User::factory()->deleteAssets()->viewAssets()->create())
            ->delete(route('hardware.destroy', $computer));

        $this->assertNotSoftDeleted($computer);
    }

    public function testCanDeleteAsset()
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->deleteAssets()->viewAssets()->create())
            ->delete(route('hardware.destroy', $asset))
            ->assertRedirect(route('hardware.index'));

        $this->assertSoftDeleted($asset);
    }
}
