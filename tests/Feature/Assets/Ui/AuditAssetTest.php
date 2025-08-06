<?php

use App\Models\Asset;
use App\Models\User;

test('permission required to create asset model', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('clone/hardware', Asset::factory()->create()))
        ->assertForbidden();
});

test('page can be accessed', function () {
    $this->actingAs(User::factory()->auditAssets()->create())
        ->get(route('asset.audit.create', Asset::factory()->create()))
        ->assertStatus(200);
});

test('asset audit post is redirected to asset index if redirect selection is index', function () {
    $asset = Asset::factory()->create();

    $response = $this->actingAs(User::factory()->viewAssets()->editAssets()->auditAssets()->create())
        ->from(route('asset.audit.create', $asset))
        ->post(route('asset.audit.store', $asset),
            [
                'redirect_option' => 'index',
            ])
        ->assertStatus(302)
        ->assertRedirect(route('hardware.index'));
    $this->followRedirects($response)->assertSee('success');

    $this->assertHasTheseActionLogs($asset, ['create', 'audit']);
});

test('asset audit post is redirected to asset page if redirect selection is asset', function () {
    $asset = Asset::factory()->create();

    $response = $this->actingAs(User::factory()->viewAssets()->editAssets()->auditAssets()->create())
        ->from(route('asset.audit.create', $asset))
        ->post(route('asset.audit.store', $asset),
            [
                'redirect_option' => 'item',
            ])
        ->assertStatus(302)
        ->assertRedirect(route('hardware.show', $asset));
    $this->followRedirects($response)->assertSee('success');
    $this->assertHasTheseActionLogs($asset, ['create', 'audit']);
    // WAT.
});

test('asset audit post is redirected to audit due page if redirect selection is list', function () {
    $asset = Asset::factory()->create();

    $response = $this->actingAs(User::factory()->viewAssets()->editAssets()->auditAssets()->create())
        ->from(route('asset.audit.create', $asset))
        ->post(route('asset.audit.store', $asset),
            [
                'redirect_option' => 'other_redirect',
            ])
        ->assertStatus(302)
        ->assertRedirect(route('assets.audit.due'));
    $this->followRedirects($response)->assertSee('success');
    $this->assertHasTheseActionLogs($asset, ['create', 'audit']);
});
