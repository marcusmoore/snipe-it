<?php

use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;

test('license checkout', function () {
    $authUser = User::factory()->superuser()->create();
    $this->actingAsForApi($authUser);

    $license = License::factory()->create();
    $licenseSeat = LicenseSeat::factory()->for($license)->create([
        'assigned_to' => null,
    ]);

    $targetUser = User::factory()->create();

    $payload = [
        'assigned_to' => $targetUser->id,
        'notes' => 'Checking out the seat to a user',
    ];

    $response = $this->patchJson(
        route('api.licenses.seats.update', [$license->id, $licenseSeat->id]),
        $payload);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'status' => 'success',
        ]);

    $licenseSeat->refresh();

    expect($licenseSeat->assigned_to)->toEqual($targetUser->id);
    expect($licenseSeat->notes)->toEqual('Checking out the seat to a user');
    $this->assertHasTheseActionLogs($license, ['add seats', 'create', 'checkout']);
    //FIXME - backwards
});
