<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Supplier;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeSupplierTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_suppliers_purged()
    {
        $suppliers = Supplier::factory()->count(2)->create();

        $suppliers->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('suppliers', ['id' => $suppliers->first()->id]);
        $this->assertDatabaseHas('suppliers', ['id' => $suppliers->last()->id]);
    }

    public function test_deletes_suppliers_image()
    {
        $filename = str_random() . '.jpg';

        $supplier = Supplier::factory()->create(['image' => $filename]);

        $filepath = "suppliers/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $supplier->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public function test_deletes_suppliers_uploads()
    {
        $this->markTestIncomplete();
    }
}
