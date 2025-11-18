<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Location;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeLocationTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_locations_purged()
    {
        $locations = Location::factory()->count(2)->create();

        $locations->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('locations', ['id' => $locations->first()->id]);
        $this->assertDatabaseHas('locations', ['id' => $locations->last()->id]);
    }

    public function test_deletes_locations_image()
    {
        $filename = str_random() . '.jpg';

        $location = Location::factory()->create(['image' => $filename]);

        $filepath = "locations/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $location->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public function test_deletes_location_uploads()
    {
        $filepath = 'private_uploads/locations';

        $locations = Location::factory()->count(2)->create();

        Storage::put("{$filepath}/to-remove.txt", 'contents');
        $locations->first()->logUpload("to-remove.txt", '');

        Storage::put("{$filepath}/to-keep.txt", 'contents');
        $locations->last()->logUpload("to-keep.txt", '');

        $locations->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        Storage::assertMissing("{$filepath}/to-remove.txt");
        Storage::assertExists("{$filepath}/to-keep.txt");
    }
}
