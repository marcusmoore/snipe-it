<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Manufacturer;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeManufacturerTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_manufacturers_purged()
    {
        $manufacturers = Manufacturer::factory()->count(2)->create();

        $manufacturers->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('manufacturers', ['id' => $manufacturers->first()->id]);
        $this->assertDatabaseHas('manufacturers', ['id' => $manufacturers->last()->id]);
    }

    public function test_deletes_manufacturers_image()
    {
        $filename = str_random() . '.jpg';

        $manufacturer = Manufacturer::factory()->create(['image' => $filename]);

        $filepath = "manufacturers/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $manufacturer->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }
}
