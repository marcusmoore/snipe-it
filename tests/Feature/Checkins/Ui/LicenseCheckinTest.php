<?php

use App\Models\LicenseSeat;
use App\Models\User;

test('checking in license requires correct permission', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('licenses.checkin.save', [
            'licenseId' => LicenseSeat::factory()->assignedToUser()->create()->id,
        ]))
        ->assertForbidden();
});
