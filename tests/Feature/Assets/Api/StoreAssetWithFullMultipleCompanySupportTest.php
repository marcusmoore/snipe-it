<?php

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Statuslabel;
use App\Models\User;
use Tests\Support\ProvidesDataForFullMultipleCompanySupportTesting;

uses(ProvidesDataForFullMultipleCompanySupportTesting::class);

test('adheres to full multiple companies support scoping', function (User $actor, Company $company, Closure $assertions) {
    $this->settings->enableMultipleFullCompanySupport();

    $response = $this->actingAsForApi($actor)
        ->postJson(route('api.assets.store'), [
            'asset_tag' => 'random_string',
            'company_id' => $company->id,
            'model_id' => AssetModel::factory()->create()->id,
            'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
        ]);

    $asset = Asset::withoutGlobalScopes()->findOrFail($response['payload']['id']);

    $assertions($asset);
})->with('data for full multiple company support testing');

test('handles company id being string', function (User $actor, Company $company, Closure $assertions) {
    $this->settings->enableMultipleFullCompanySupport();

    $response = $this->actingAsForApi($actor)
        ->postJson(route('api.assets.store'), [
            'asset_tag' => 'random_string',
            'company_id' => (string) $company->id,
            'model_id' => AssetModel::factory()->create()->id,
            'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
        ]);

    $asset = Asset::withoutGlobalScopes()->findOrFail($response['payload']['id']);

    $assertions($asset);
})->with('data for full multiple company support testing');
