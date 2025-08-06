<?php

use App\Importer\AssetImporter;
use App\Models\Statuslabel;
use function Livewire\invade;


test('uses first deployable status label as default if one exists', function () {
    Statuslabel::truncate();

    $pendingStatusLabel = Statuslabel::factory()->pending()->create();
    $readyToDeployStatusLabel = Statuslabel::factory()->readyToDeploy()->create();

    $importer = new AssetImporter('assets.csv');

    expect(invade($importer)->defaultStatusLabelId)->toEqual($readyToDeployStatusLabel->id);
});

test('uses first status label as default if deployable status label does not exist', function () {
    Statuslabel::truncate();

    $statusLabel = Statuslabel::factory()->pending()->create();

    $importer = new AssetImporter('assets.csv');

    expect(invade($importer)->defaultStatusLabelId)->toEqual($statusLabel->id);
});

test('creates default status label if one does not exist', function () {
    Statuslabel::truncate();

    expect(Statuslabel::count())->toEqual(0);

    $importer = new AssetImporter('assets.csv');

    expect(Statuslabel::count())->toEqual(1);

    expect(invade($importer)->defaultStatusLabelId)->toEqual(Statuslabel::first()->id);
});
