<?php

use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('asset api accepts file upload', function () {
    // Upload a file to an asset
    // Create an asset to work with
    $asset = Asset::factory()->count(1)->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    //Upload a file
    $this->actingAsForApi($user)
               ->post(
                  route('api.assets.files.store', ['asset_id' => $asset[0]["id"]]), [
   		       'file' => [UploadedFile::fake()->create("test.jpg", 100)]
   	       ])
   	       ->assertOk();
});

test('asset api lists files', function () {
    // List all files on an asset
    // Create an asset to work with
    $asset = Asset::factory()->count(1)->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    // List the files
    $this->actingAsForApi($user)
               ->getJson(
   		    route('api.assets.files.index', ['asset_id' => $asset[0]["id"]]))
                   ->assertOk()
   		->assertJsonStructure([
                       'status',
   		    'messages',
   		    'payload',
   		]);
});

test('asset api downloads file', function () {
    // Download a file from an asset
    // Create an asset to work with
    $asset = Asset::factory()->count(1)->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    //Upload a file
    $this->actingAsForApi($user)
               ->post(
                  route('api.assets.files.store', ['asset_id' => $asset[0]["id"]]), [
   		       'file' => [UploadedFile::fake()->create("test.jpg", 100)]
   	       ])
   	       ->assertOk();

    // List the files to get the file ID
    $result = $this->actingAsForApi($user)
               ->getJson(
   		    route('api.assets.files.index', ['asset_id' => $asset[0]["id"]]))
                   ->assertOk();

    // Get the file
    $this->actingAsForApi($user)
               ->get(
                  route('api.assets.files.show', [
                      'asset_id' => $asset[0]["id"],
                      'file_id' => $result->decodeResponseJson()->json()["payload"][0]["id"],
   	       ]))
   	       ->assertOk();
});

test('asset api deletes file', function () {
    // Delete a file from an asset
    // Create an asset to work with
    $asset = Asset::factory()->count(1)->create();

    // Create a superuser to run this as
    $user = User::factory()->superuser()->create();

    //Upload a file
    $this->actingAsForApi($user)
               ->post(
                  route('api.assets.files.store', ['asset_id' => $asset[0]["id"]]), [
   		       'file' => [UploadedFile::fake()->create("test.jpg", 100)]
   	       ])
   	       ->assertOk();

    // List the files to get the file ID
    $result = $this->actingAsForApi($user)
               ->getJson(
   		    route('api.assets.files.index', ['asset_id' => $asset[0]["id"]]))
                   ->assertOk();

    // Delete the file
    $this->actingAsForApi($user)
               ->delete(
                  route('api.assets.files.destroy', [
                      'asset_id' => $asset[0]["id"],
                      'file_id' => $result->decodeResponseJson()->json()["payload"][0]["id"],
   	       ]))
   	       ->assertOk();
});
