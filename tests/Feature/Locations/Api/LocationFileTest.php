<?php

use App\Models\Location;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('location api accepts file upload', function () {
    // Create a model to work with
    $location = Location::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    //Upload a file
    $this->actingAsForApi($user)
        ->post(
            route('api.files.store', ['object_type' => 'locations', 'id' => $location->id]), [
            'file' => [UploadedFile::fake()->create("test.jpg", 100)]
            ]
        )
        ->assertOk();
});

test('location api lists files', function () {
    // List all files on a model
    // Create a model to work with
    $location = Location::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    // List the files
    $this->actingAsForApi($user)
        ->getJson(
            route('api.files.index', ['object_type' => 'locations', 'id' => $location->id])
        )
        ->assertOk()
        ->assertJsonStructure(
            [
            'rows',
            'total',
            ]
        );
});

test('location fails if invalid type passed in url', function () {
    // List all files on a model
    // Create an model to work with
    $location = Location::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    // List the files
    $this->actingAsForApi($user)
        ->getJson(
            route('api.files.index', ['object_type' => 'shibboleeeeeet', 'id' => $location->id])
        )
        ->assertStatus(404);
});

test('location fails if invalid id passed in url', function () {
    // List all files on a model
    // Create an model to work with
    $location = Location::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    // List the files
    $this->actingAsForApi($user)
        ->getJson(
            route('api.files.index', ['object_type' => 'locations', 'id' => 100000])
        )
        ->assertOk()
        ->assertStatusMessageIs('error');
});

test('location api downloads file', function () {
    // Download a file from a model
    // Create a model to work with
    $location = Location::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    // Upload a file
    $this->actingAsForApi($user)
        ->post(
            route('api.files.store', ['object_type' => 'locations', 'id' => $location->id]), [
            'file' => [UploadedFile::fake()->create("test.jpg", 100)],
            ]
        )
        ->assertOk()
        ->assertJsonStructure(
            [
            'status',
            'messages',
            ]
        );

    // Upload a file with notes
    $this->actingAsForApi($user)
        ->post(
            route('api.files.store', ['object_type' => 'locations', 'id' => $location->id]), [
            'file' => [UploadedFile::fake()->create("test.jpg", 100)],
            'notes' => 'manual'
            ]
        )
        ->assertOk()
        ->assertJsonStructure(
            [
            'status',
            'messages',
            ]
        );

    // List the files to get the file ID
    $result = $this->actingAsForApi($user)
        ->getJson(
            route('api.files.index', ['object_type' => 'locations', 'id' => $location->id, 'order' => 'asc'])
        )
        ->assertOk()
        ->assertJsonStructure(
            [
            'total',
            'rows'=>[
                '*' => [
                    'id',
                    'filename',
                    'url',
                    'created_by',
                    'created_at',
                    'deleted_at',
                    'note',
                    'available_actions'
                ]
            ]
            ]
        )
        ->assertJsonPath('rows.0.note', null)
        ->assertJsonPath('rows.1.note', 'manual');

    // Get the file
    $this->actingAsForApi($user)
        ->get(
            route(
                'api.files.show', [
                'object_type' => 'locations',
                'id' => $location->id,
                'file_id' => $result->decodeResponseJson()->json()["rows"][0]["id"],
                ]
            )
        )
        ->assertOk();
});

test('location api deletes file', function () {
    // Delete a file from a model
    // Create a model to work with
    $location = Location::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    //Upload a file
    $this->actingAsForApi($user)
        ->post(
            route('api.files.store', ['object_type' => 'locations', 'id' => $location->id]), [
            'file' => [UploadedFile::fake()->create("test.jpg", 100)]
            ]
        )
        ->assertOk();

    // List the files to get the file ID
    $result = $this->actingAsForApi($user)
        ->getJson(
            route('api.files.index', ['object_type' => 'locations', 'id' => $location->id])
        )
        ->assertOk();

    // Delete the file
    $this->actingAsForApi($user)
        ->delete(
            route(
                'api.files.destroy', [
                'object_type' => 'locations',
                'id' => $location->id,
                'file_id' => $result->decodeResponseJson()->json()["rows"][0]["id"],
                ]
            )
        )
        ->assertOk()
        ->assertJsonStructure(
            [
            'status',
            'messages',
            ]
        );
});
