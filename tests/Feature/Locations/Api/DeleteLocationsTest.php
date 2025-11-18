<?php

namespace Tests\Feature\Locations\Api;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteLocationsTest extends TestCase implements TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $location = Location::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->deleteJson(route('api.locations.destroy', $location))
            ->assertForbidden();

        $this->assertNotSoftDeleted($location);
    }

    public function testErrorReturnedViaApiIfLocationDoesNotExist()
    {
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.locations.destroy', 'invalid-id'))
            ->assertOk()
            ->assertStatus(200)
            ->assertStatusMessageIs('error')
            ->json();

    }

    public function testErrorReturnedViaApiIfLocationIsAlreadyDeleted()
    {
        $location = Location::factory()->deletedLocation()->create();
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.locations.destroy', $location->id))
            ->assertOk()
            ->assertStatus(200)
            ->assertStatusMessageIs('error')
            ->json();
    }

    public function testDisallowLocationDeletionViaApiIfStillHasPeople()
    {
        $location = Location::factory()->create();
        User::factory()->count(5)->create(['location_id' => $location->id]);

        $this->assertFalse($location->isDeletable());

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.locations.destroy', $location->id))
            ->assertOk()
            ->assertStatus(200)
            ->assertStatusMessageIs('error')
            ->json();
        $this->assertNotSoftDeleted($location);
    }

    public function testDisallowLocationDeletionViaApiIfStillHasChildLocations()
    {
        $parent = Location::factory()->create();
        Location::factory()->count(5)->create(['parent_id' => $parent->id]);
        $this->assertFalse($parent->isDeletable());

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.locations.destroy', $parent->id))
            ->assertOk()
            ->assertStatus(200)
            ->assertStatusMessageIs('error')
            ->json();
        $this->assertNotSoftDeleted($parent);
    }

    public function testDisallowLocationDeletionViaApiIfStillHasAssetsAssigned()
    {
        $location = Location::factory()->create();
        Asset::factory()->count(5)->assignedToLocation($location)->create();

        $this->assertFalse($location->isDeletable());

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.locations.destroy', $location->id))
            ->assertOk()
            ->assertStatus(200)
            ->assertStatusMessageIs('error')
            ->json();
        $this->assertNotSoftDeleted($location);
    }

    public function testDisallowLocationDeletionViaApiIfStillHasAssetsAsLocation()
    {
        $location = Location::factory()->create();
        Asset::factory()->count(5)->create(['location_id' => $location->id]);

        $this->assertFalse($location->isDeletable());

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.locations.destroy', $location->id))
            ->assertOk()
            ->assertStatus(200)
            ->assertStatusMessageIs('error')
            ->json();
        $this->assertNotSoftDeleted($location);
    }

    public function testDisallowLocationDeletionViaApiIfStillHasConsumablesAsLocation()
    {
        $location = Location::factory()->create();
        Consumable::factory()->count(5)->create(['location_id' => $location->id]);

        $this->assertFalse($location->isDeletable());

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.locations.destroy', $location->id))
            ->assertOk()
            ->assertStatus(200)
            ->assertStatusMessageIs('error')
            ->json();
        $this->assertNotSoftDeleted($location);
    }

    public function testDisallowLocationDeletionViaApiIfStillHasComponentsAsLocation()
    {
        $location = Location::factory()->create();
        Component::factory()->count(5)->create(['location_id' => $location->id]);

        $this->assertFalse($location->isDeletable());

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.locations.destroy', $location->id))
            ->assertOk()
            ->assertStatus(200)
            ->assertStatusMessageIs('error')
            ->json();

        $this->assertNotSoftDeleted($location);
    }

    public function testDisallowLocationDeletionViaApiIfStillHasAccessoriesAssigned()
    {
        $location = Location::factory()->create();
        Accessory::factory()->count(5)->checkedOutToLocation($location)->create();

        $this->assertFalse($location->isDeletable());

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.locations.destroy', $location->id))
            ->assertOk()
            ->assertStatus(200)
            ->assertStatusMessageIs('error')
            ->json();
        $this->assertNotSoftDeleted($location);
    }

    public function testDisallowLocationDeletionViaApiIfStillHasAccessoriesAsLocation()
    {
        $location = Location::factory()->create();
        Accessory::factory()->count(5)->create(['location_id' => $location->id]);

        $this->assertFalse($location->isDeletable());

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.locations.destroy', $location->id))
            ->assertOk()
            ->assertStatus(200)
            ->assertStatusMessageIs('error')
            ->json();

        $this->assertNotSoftDeleted($location);
    }

    public function testCanDeleteLocation()
    {
        $location = Location::factory()->create();

        $this->actingAsForApi(User::factory()->deleteLocations()->create())
            ->deleteJson(route('api.locations.destroy', $location->id))
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted($location);
    }

    public function test_preserves_image_in_case_location_restored()
    {
        Storage::fake('public');

        $location = Location::factory()->create(['image' => 'image.jpg']);

        Storage::disk('public')->put('locations/image.jpg', 'content');

        Storage::disk('public')->assertExists('locations/image.jpg');

        $this->actingAsForApi(User::factory()->deleteLocations()->create())
            ->deleteJson(route('api.locations.destroy', $location->id))
            ->assertStatusMessageIs('success');

        Storage::disk('public')->assertExists('locations/image.jpg');

        $this->assertEquals('image.jpg', $location->fresh()->image);
    }

    public function test_preserves_uploads_in_case_model_restored()
    {
        Storage::fake('public');

        $filepath = 'private_uploads/locations';

        $location = Location::factory()->create();

        Storage::put("{$filepath}/to-keep.txt", 'contents');
        $location->logUpload("to-keep.txt", '');

        $this->actingAsForApi(User::factory()->deleteLocations()->create())
            ->deleteJson(route('api.locations.destroy', $location->id))
            ->assertStatusMessageIs('success');

        Storage::assertExists("{$filepath}/to-keep.txt");
    }
}
