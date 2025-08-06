<?php

use App\Models\Location;
use App\Models\User;
use App\Models\Actionlog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;


test('permission required to store location', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('locations.store'), [
            'name' => 'Test Location',
        ])
        ->assertStatus(403)
        ->assertForbidden();
});

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('locations.edit', Location::factory()->create()))
        ->assertOk();
});

test('user can edit locations', function () {
    $location = Location::factory()->create(['name' => 'Test Location']);
    expect(Location::where('name', 'Test Location')->exists())->toBeTrue();

    $response = $this->actingAs(User::factory()->superuser()->create())
        ->put(route('locations.update', ['location' => $location]), [
            'name' => 'Test Location Edited',
            'notes' => 'Test Note Edited',
        ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('locations.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect(Location::where('name', 'Test Location Edited')->where('notes', 'Test Note Edited')->exists())->toBeTrue();
});

test('user cannot edit locations to make them their own parent', function () {
    $location = Location::factory()->create();

    $response = $this->actingAs(User::factory()->superuser()->create())
        ->from(route('locations.edit', $location))
        ->put(route('locations.update', $location), [
            'name' => 'Test Location',
            'parent_id' => $location->id,
        ])
        ->assertRedirect(route('locations.edit', ['location' => $location]));

    $this->followRedirects($response)->assertSee(trans('general.error'));
    expect(Location::where('name', 'Test Location')->exists())->toBeFalse();
});

test('user cannot edit locations with invalid parent', function () {
    $location = Location::factory()->create();
    $response = $this->actingAs(User::factory()->superuser()->create())
        ->from(route('locations.edit', $location))
        ->put(route('locations.update', ['location' => $location]), [
            'name' => 'Test Location',
            'parent_id' => '100000000'
        ])
        ->assertRedirect(route('locations.edit', ['location' => $location->id]));

    $this->followRedirects($response)->assertSee(trans('general.error'));
    expect(Location::where('name', 'Test Location')->exists())->toBeFalse();
});

test('file is uploaded and logged', function () {
    $location = Location::factory()->create();
    Storage::fake('local');
    $file = UploadedFile::fake()->image('file.jpg', 100, 100)->size(100);

    $this->actingAs(User::factory()->superuser()->create())
        ->post(route('upload/locations', $location), [
        'file' => [$file],
        'notes' => 'Test Upload',
    ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors();

    $location->refresh();

    $logentry = Actionlog::where('item_type', Location::class)
        ->where('item_id', $location->id)
        ->where('action_type', 'uploaded')
        ->first();

    expect(Actionlog::where('item_type', Location:: class)->where('item_id', $location->id)->where('filename', $logentry->filename)->exists())->toBeTrue();

    // Assert the file was stored...
    // This doesn't work with the way we handle files :( Should try to fix this.
    // Storage::disk('local')->assertExists($logentry->filename);
});
