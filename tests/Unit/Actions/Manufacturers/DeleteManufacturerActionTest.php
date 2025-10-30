<?php

namespace Tests\Unit\Actions\Manufacturers;

use App\Actions\Manufacturers\DeleteManufacturerAction;
use App\Models\Manufacturer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteManufacturerActionTest extends TestCase
{
    public function test_deletes_manufacturer()
    {
        $manufacturer = Manufacturer::factory()->create();

        DeleteManufacturerAction::run($manufacturer);

        $this->assertSoftDeleted($manufacturer);
    }

    public function test_preserves_image_in_case_manufacturer_restored()
    {
        Storage::fake('public');

        $filename = 'temp-file.jpg';
        $filepath = 'manufacturers/' . $filename;

        Storage::disk('public')->put($filepath, 'contents');

        DeleteManufacturerAction::run(Manufacturer::factory()->create(['image' => $filename]));

        Storage::disk('public')->assertExists($filepath);
    }
}
