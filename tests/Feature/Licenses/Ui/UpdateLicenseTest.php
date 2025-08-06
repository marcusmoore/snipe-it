<?php

use App\Models\Category;
use App\Models\License;
use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('licenses.edit', License::factory()->create()->id))
        ->assertOk();
});

test('can update license seats', function () {
    $admin = User::factory()->superuser()->create();
    $license_category = Category::factory()->forLicenses()->create()->id;
    $response = $this->actingAs($admin)
        ->from(route('licenses.create'))
        ->post(route('licenses.store'), [
            'name' => 'Test Update License',
            'seats' => '9999',
            'category_id' => $license_category,
        ]);
    $response->assertStatus(302);
    $license = License::where('name', 'Test Update License')->sole();
    expect($license)->not->toBeNull();

    $this->actingAs($admin)
        ->put(route('licenses.update', $license->id), [
            'name' => 'Test Update License',
            'seats' => '19999',
            'category_id' => $license_category,
        ])
        ->assertStatus(302);

    $license->refresh();
    expect($license->seats)->toEqual($license->licenseseats()->count());
    expect(19999)->toEqual($license->licenseseats()->count());
});

test('cannot update license seats too much', function () {
    $admin = User::factory()->superuser()->create();
    $license_category = Category::factory()->forLicenses()->create()->id;
    $response = $this->actingAs($admin)
        ->from(route('licenses.create'))
        ->post(route('licenses.store'), [
            'name' => 'Test Update License',
            'seats' => '9999',
            'category_id' => $license_category,
        ]);
    $response->assertStatus(302);
    $license = License::where('name', 'Test Update License')->sole();
    expect($license)->not->toBeNull();

    $this->actingAs($admin)
        ->put(route('licenses.update', $license->id), [
            'name' => 'Test Update License',
            'seats' => '29999',
            'category_id' => $license_category,
        ])
        ->assertStatus(302);

    $license->refresh();
    expect($license->seats)->toEqual($license->licenseseats()->count());
    expect(9999)->toEqual($license->licenseseats()->count());
});

test('can remove license seats', function () {
    $admin = User::factory()->superuser()->create();
    $license_category = Category::factory()->forLicenses()->create()->id;
    $response = $this->actingAs($admin)
        ->from(route('licenses.create'))
        ->post(route('licenses.store'), [
            'name' => 'Test Remove License Seats',
            'seats' => '9999',
            'category_id' => $license_category,
        ]);
    $response->assertStatus(302);
    $license = License::where('name', 'Test Remove License Seats')->sole();
    expect($license)->not->toBeNull();

    $this->actingAs($admin)
        ->put(route('licenses.update', $license->id), [
            'name' => 'Test Remove License Seats',
            'seats' => '5000',
            'category_id' => $license_category,
        ])
        ->assertStatus(302);

    $license->refresh();
    expect($license->seats)->toEqual($license->licenseseats()->count());
    expect(5000)->toEqual($license->licenseseats()->count());
});
