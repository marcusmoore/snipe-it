<?php

use App\Events\CheckoutableCheckedOut;
use App\Listeners\LogListener;
use App\Models\Asset;
use App\Models\User;

test('logs entry on checkoutable checked out', function () {
    $asset = Asset::factory()->create();
    $checkedOutTo = User::factory()->create();
    $checkedOutBy = User::factory()->create();

    // Simply to ensure `user_id` is set in the action log
    $this->actingAs($checkedOutBy);

    (new LogListener())->onCheckoutableCheckedOut(new CheckoutableCheckedOut(
        $asset,
        $checkedOutTo,
        $checkedOutBy,
        'A simple note...',
    ));

    $this->assertDatabaseHas('action_logs', [
        'action_type' => 'checkout',
        'user_id' => $checkedOutBy->id,
        'target_id' => $checkedOutTo->id,
        'target_type' => User::class,
        'item_id' => $asset->id,
        'item_type' => Asset::class,
        'note' => 'A simple note...',
    ]);
});
