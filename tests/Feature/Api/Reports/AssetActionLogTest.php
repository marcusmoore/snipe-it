<?php

namespace Tests\Feature\Api\Reports;

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Tests\Support\InteractsWithSettings;
use Tests\TestCase;

class AssetActionLogTest extends TestCase
{
    use InteractsWithSettings;

    public function testAssetHistoryThatContainsDeletedCompanyRenders()
    {
        $company = Company::factory()->create();
        $asset = Asset::factory()->create(['company_id' => $company->id]);

        // grab fresh instance to trigger updating observer
        $asset->refresh();

        $asset->update(['company_id' => Company::factory()->create()->id]);

        $company->forceDelete();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->get(route('api.activity.index', [
                'item_id' => $asset->id,
                'item_type' => 'asset',
            ]))
            ->assertOk();
    }
}
