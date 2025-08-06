<?php

use App\Models\Statuslabel;

test('rtdstatuslabel add', function () {
    $statuslabel = Statuslabel::factory()->rtd()->create();
    $this->assertModelExists($statuslabel);
});

test('pending statuslabel add', function () {
    $statuslabel = Statuslabel::factory()->pending()->create();
    $this->assertModelExists($statuslabel);
});

test('archived statuslabel add', function () {
    $statuslabel = Statuslabel::factory()->archived()->create();
    $this->assertModelExists($statuslabel);
});

test('out for repair statuslabel add', function () {
    $statuslabel = Statuslabel::factory()->outForRepair()->create();
    $this->assertModelExists($statuslabel);
});

test('broken statuslabel add', function () {
    $statuslabel = Statuslabel::factory()->broken()->create();
    $this->assertModelExists($statuslabel);
});

test('lost statuslabel add', function () {
    $statuslabel = Statuslabel::factory()->lost()->create();
    $this->assertModelExists($statuslabel);
});
