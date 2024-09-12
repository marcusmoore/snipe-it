<?php

namespace Tests\Feature\AssetMaintenances\Api;

use App\Models\AssetMaintenance;
use App\Models\Company;
use App\Models\User;
use Tests\Concerns\TestsMultipleFullCompanySupport;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteAssetMaintenancesTest extends TestCase implements TestsMultipleFullCompanySupport, TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $assetMaintenance = AssetMaintenance::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->deleteJson(route('api.maintenances.destroy', $assetMaintenance))
            ->assertForbidden();
    }

    public function testCanDeleteAssetMaintenance()
    {
        $assetMaintenance = AssetMaintenance::factory()->create();

        $this->actingAsForApi(User::factory()->editAssets()->create())
            ->deleteJson(route('api.maintenances.destroy', $assetMaintenance))
            ->assertStatusMessageIs('success');

        $this->assertTrue($assetMaintenance->fresh()->trashed());
    }

    public function testAdheresToMultipleFullCompanySupportScoping()
    {
        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $assetMaintenanceA = AssetMaintenance::factory()->create();
        $assetMaintenanceB = AssetMaintenance::factory()->create();
        $assetMaintenanceC = AssetMaintenance::factory()->create();

        $assetMaintenanceA->asset->update(['company_id' => $companyA->id]);
        $assetMaintenanceB->asset->update(['company_id' => $companyB->id]);
        $assetMaintenanceC->asset->update(['company_id' => $companyB->id]);

        $superUser = $companyA->users()->save(User::factory()->superuser()->make());
        $userInCompanyA = $companyA->users()->save(User::factory()->editAssets()->make());
        $userInCompanyB = $companyB->users()->save(User::factory()->editAssets()->make());

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($userInCompanyA)
            ->deleteJson(route('api.maintenances.destroy', $assetMaintenanceB))
            ->assertStatusMessageIs('error');

        $this->actingAsForApi($userInCompanyB)
            ->deleteJson(route('api.maintenances.destroy', $assetMaintenanceA))
            ->assertStatusMessageIs('error');

        $this->actingAsForApi($superUser)
            ->deleteJson(route('api.maintenances.destroy', $assetMaintenanceC))
            ->assertStatusMessageIs('success');

        $this->assertNull($assetMaintenanceA->fresh()->deleted_at, 'Asset Maintenance unexpectedly deleted');
        $this->assertNull($assetMaintenanceB->fresh()->deleted_at, 'Asset Maintenance unexpectedly deleted');
        $this->assertNotNull($assetMaintenanceC->fresh()->deleted_at, 'Asset Maintenance was not deleted');
    }
}