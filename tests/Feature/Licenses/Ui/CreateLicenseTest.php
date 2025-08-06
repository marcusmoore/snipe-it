<?php

use App\Models\Category;
use App\Models\License;
use App\Models\Depreciation;
use App\Models\User;

test('permission required to view license', function () {
    $license = License::factory()->create();
    $this->actingAs(User::factory()->create())
        ->get(route('licenses.create', $license))
        ->assertForbidden();
});

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('licenses.create'))
        ->assertOk();
});

test('license without purchase date fails validation', function () {
    $response = $this->actingAs(User::factory()->superuser()->create())
        ->from(route('licenses.create'))
        ->post(route('licenses.store'), [
            'name' => 'Test Invalid License',
            'seats' => '10',
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'depreciation_id' => Depreciation::factory()->create()->id
        ]);
    $response->assertStatus(302);
    $response->assertRedirect(route('licenses.create'));
    $response->assertInvalid(['purchase_date']);
    $response->assertSessionHasErrors(['purchase_date']);
    $this->followRedirects($response)->assertSee(trans('general.error'));
    expect(License::where('name', 'Test Invalid License')->exists())->toBeFalse();
});

test('license create', function () {
    $response = $this->actingAs(User::factory()->superuser()->create())
        ->from(route('licenses.create'))
        ->post(route('licenses.store'), [
            'name' => 'Test Valid License',
            'seats' => '10',
            'category_id' => Category::factory()->forLicenses()->create()->id,
        ]);
    $response->assertStatus(302);
    $license = License::where('name', 'Test Valid License')->sole();
    expect($license)->not->toBeNull();

    //$license->assetlog()->has_one_of_();
    $this->assertDatabaseHas('action_logs', ['action_type' => 'create', 'item_id' => $license->id, 'item_type' => License::class]);
    $this->assertDatabaseHas('action_logs', ['action_type' => 'add seats', 'item_id' => $license->id, 'item_type' => License::class]);
    expect(10)->toEqual($license->licenseseats()->count());
    //test log entries? Sure.
});

test('too many seats license create', function () {
    $response = $this->actingAs(User::factory()->superuser()->create())
        ->from(route('licenses.create'))
        ->post(route('licenses.store'), [
            'name' => 'Test Valid License',
            'seats' => '100000',
            'category_id' => Category::factory()->forLicenses()->create()->id,
        ]);
    $response->assertStatus(302);
    $license = License::where('name', 'Test Valid License')->first();
    expect($license)->toBeNull();

    //$license->assetlog()->has_one_of_();
    //        $this->assertDatabaseMissing('action_logs', ['action_type' => 'create', 'item_id' => $license->id, 'item_type' => License::class]);
    //        $this->assertDatabaseMissing('action_logs', ['action_type' => 'add seats', 'item_id' => $license->id, 'item_type' => License::class]);
    //test log entries? Sure.
});
