<?php

namespace Tests\Feature\Manufacturers\Ui;

use App\Models\Accessory;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteManufacturersTest extends TestCase implements TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $this->actingAs(User::factory()->create())
            ->delete(route('categories.destroy', Category::factory()->create()))
            ->assertForbidden();
    }

    public function test_manufacturer_cannot_be_deleted_if_models_still_associated()
    {
        $manufacturer = Manufacturer::factory()->create();
        Accessory::factory()->for($manufacturer)->create();

        $this->actingAs(User::factory()->deleteManufacturers()->create())
            ->delete(route('manufacturers.destroy', $manufacturer));

        $this->assertNotSoftDeleted($manufacturer);
    }

    public function test_manufacturer_can_be_deleted()
    {
        $manufacturer = Manufacturer::factory()->create();

        $this->assertDatabaseHas('manufacturers', ['id' => $manufacturer->id]);

        $this->actingAs(User::factory()->deleteManufacturers()->create())
            ->delete(route('manufacturers.destroy', $manufacturer))
            ->assertRedirect(route('manufacturers.index'));

        $this->assertSoftDeleted($manufacturer);
    }

    public function test_preserves_image_in_case_manufacturer_restored()
    {
        Storage::fake('public');

        $filepath = 'manufacturers/temp-file.jpg';

        Storage::disk('public')->put($filepath, 'contents');

        $manufacturer = Manufacturer::factory()->create(['image' => 'temp-file.jpg']);

        $this->actingAs(User::factory()->deleteManufacturers()->create())
            ->delete(route('manufacturers.destroy', $manufacturer))
            ->assertSessionHas('success');

        Storage::disk('public')->assertExists($filepath);
    }
}
