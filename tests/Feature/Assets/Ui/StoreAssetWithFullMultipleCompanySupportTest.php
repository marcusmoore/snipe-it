<?php

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Statuslabel;
use Tests\Support\ProvidesDataForFullMultipleCompanySupportTesting;

uses(ProvidesDataForFullMultipleCompanySupportTesting::class);

test('adheres to full multiple companies support scoping', function ($actor, $company, $assertions) {
    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAs($actor)
        ->post(route('hardware.store'), [
            'asset_tags' => ['1' => '1234'],
            'model_id' => AssetModel::factory()->create()->id,
            'status_id' => Statuslabel::factory()->create()->id,
            'company_id' => $company->id,
        ]);

    $asset = Asset::where('asset_tag', '1234')->sole();

    $assertions($asset);
})->with('data for full multiple company support testing');
