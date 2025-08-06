<?php

use App\Models\AssetModel;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('asset model api accepts file upload', function () {
    // Upload a file to a model
    // Create a model to work with
    $model = AssetModel::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    //Upload a file
    $this->actingAsForApi($user)
        ->post(
            route('api.files.store', ['object_type' => 'models', 'id' => $model->id]), [
            'file' => [UploadedFile::fake()->create("test.jpg", 100)]
            ]
        )
        ->assertOk();
});

test('asset model api lists files', function () {
    // List all files on a model
    // Create an model to work with
    $model = AssetModel::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    // List the files
    $this->actingAsForApi($user)
        ->getJson(
            route('api.files.index', ['object_type' => 'models', 'id' => $model->id])
        )
        ->assertOk()
        ->assertJsonStructure(
            [
            'rows',
            'total',
            ]
        );
});

test('asset model fails if invalid type passed in url', function () {
    // List all files on a model
    // Create an model to work with
    $model = AssetModel::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    // List the files
    $this->actingAsForApi($user)
        ->getJson(
            route('api.files.index', ['object_type' => 'shibboleeeeeet', 'id' => $model->id])
        )
        ->assertStatus(404);
});

test('asset model fails if invalid id passed in url', function () {
    // List all files on a model
    // Create an model to work with
    $model = AssetModel::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    // List the files
    $this->actingAsForApi($user)
        ->getJson(
            route('api.files.index', ['object_type' => 'models', 'id' => 100000])
        )
        ->assertOk()
        ->assertStatusMessageIs('error');
});

test('asset model api downloads file', function () {
    // Download a file from a model
    // Create a model to work with
    $model = AssetModel::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    // Upload a file
    $this->actingAsForApi($user)
        ->post(
            route('api.files.store', ['object_type' => 'models', 'id' => $model->id]), [
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
            route('api.files.store', ['object_type' => 'models', 'id' => $model->id]), [
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
            route('api.files.index', ['object_type' => 'models', 'id' => $model->id, 'order' => 'asc'])
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
                'object_type' => 'models',
                'id' => $model->id,
                'file_id' => $result->decodeResponseJson()->json()["rows"][0]["id"],
                ]
            )
        )
        ->assertOk();
});

test('asset model api deletes file', function () {
    // Delete a file from a model
    // Create a model to work with
    $model = AssetModel::factory()->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    //Upload a file
    $this->actingAsForApi($user)
        ->post(
            route('api.files.store', ['object_type' => 'models', 'id' => $model->id]), [
            'file' => [UploadedFile::fake()->create("test.jpg", 100)]
            ]
        )
        ->assertOk();

    // List the files to get the file ID
    $result = $this->actingAsForApi($user)
        ->getJson(
            route('api.files.index', ['object_type' => 'models', 'id' => $model->id])
        )
        ->assertOk();

    // Delete the file
    $this->actingAsForApi($user)
        ->delete(
            route(
                'api.files.destroy', [
                'object_type' => 'models',
                'id' => $model->id,
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
