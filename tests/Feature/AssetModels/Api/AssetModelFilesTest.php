<?php

use App\Models\AssetModel;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('asset model api accepts file upload', function () {
    // Upload a file to a model
    // Create a model to work with
    $model = AssetModel::factory()->count(1)->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    //Upload a file
    $this->actingAsForApi($user)
               ->post(
                  route('api.models.files.store', ['model_id' => $model[0]["id"]]), [
   		       'file' => [UploadedFile::fake()->create("test.jpg", 100)]
   	       ])
   	       ->assertOk();
});

test('asset model api lists files', function () {
    // List all files on a model
    // Create an model to work with
    $model = AssetModel::factory()->count(1)->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    // List the files
    $this->actingAsForApi($user)
               ->getJson(
   		    route('api.models.files.index', ['model_id' => $model[0]["id"]]))
                   ->assertOk()
   		->assertJsonStructure([
                       'status',
   		    'messages',
   		    'payload',
   		]);
});

test('asset model api downloads file', function () {
    // Download a file from a model
    // Create a model to work with
    $model = AssetModel::factory()->count(1)->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    //Upload a file
    $this->actingAsForApi($user)
               ->post(
                  route('api.models.files.store', ['model_id' => $model[0]["id"]]), [
   		       'file' => [UploadedFile::fake()->create("test.jpg", 100)]
   	       ])
   	       ->assertOk();

    // List the files to get the file ID
    $result = $this->actingAsForApi($user)
               ->getJson(
   		    route('api.models.files.index', ['model_id' => $model[0]["id"]]))
                   ->assertOk();

    // Get the file
    $this->actingAsForApi($user)
               ->get(
                  route('api.models.files.show', [
                      'model_id' => $model[0]["id"],
                      'file_id' => $result->decodeResponseJson()->json()["payload"][0]["id"],
   	       ]))
   	       ->assertOk();
});

test('asset model api deletes file', function () {
    // Delete a file from a model
    // Create a model to work with
    $model = AssetModel::factory()->count(1)->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    //Upload a file
    $this->actingAsForApi($user)
               ->post(
                  route('api.models.files.store', ['model_id' => $model[0]["id"]]), [
   		       'file' => [UploadedFile::fake()->create("test.jpg", 100)]
   	       ])
   	       ->assertOk();

    // List the files to get the file ID
    $result = $this->actingAsForApi($user)
               ->getJson(
   		    route('api.models.files.index', ['model_id' => $model[0]["id"]]))
                   ->assertOk();

    // Delete the file
    $this->actingAsForApi($user)
               ->delete(
                  route('api.models.files.destroy', [
                      'model_id' => $model[0]["id"],
                      'file_id' => $result->decodeResponseJson()->json()["payload"][0]["id"],
   	       ]))
   	       ->assertOk();
});
