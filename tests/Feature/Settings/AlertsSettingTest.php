<?php

use App\Models\Asset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Setting;


test('permission required to view alert settings', function () {
    $asset = Asset::factory()->create();
    $this->actingAs(User::factory()->create())
        ->get(route('settings.alerts.index'))
        ->assertForbidden();
});
