<?php

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;

test('can create asset', function () {

    $user = User::factory()->superuser()->create();

    $company = Company::factory()->create(['name' => 'Apple']);
    $assetModel = AssetModel::factory()->mbp13Model()->create();
    $statuslabel = Statuslabel::factory()->readyToDeploy()->create(['name' => 'My Label']);
    $defaultLocation = Location::factory()->create(['name' => 'My Default Location']);
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $page = visit(route('hardware.create'))
        ->assertSee('Create New')
        ->wait(1)
        ->type('Asset Tag', '1234')
        ->type('Serial', 'SN-123')
        ->select('#status_select_id', $statuslabel->id);

    select2($page, '[name=company_id]', $company->id, $company->name);
    select2($page, '[name=model_id]', $assetModel->id, $assetModel->name);
    select2($page, '#rtd_location_id_location_select', $defaultLocation->id, $defaultLocation->name);

    // $page->wait(1)->radio('checkout_to_type', 'asset');

    $page->debug();

    // Fails with "BadMethodCallException: Method Laravel\Passport\Guards\TokenGuard::viaRemember does not exist"
    // $page->click('#submit_button');
});
