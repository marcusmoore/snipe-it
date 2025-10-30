<?php

namespace Tests\Unit\Actions\Suppliers;

use App\Actions\Suppliers\DestroySupplierAction;
use App\Models\Supplier;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DestroySupplierActionTest extends TestCase
{
    public function test_soft_deletes_supplier()
    {
        $supplier = Supplier::factory()->create();

        DestroySupplierAction::run($supplier);

        $this->assertSoftDeleted($supplier);
    }

    public function test_preserves_image_in_case_supplier_restored()
    {
        Storage::fake('public');

        $filename = 'temp-file.jpg';
        $filepath = 'suppliers/' . $filename;

        Storage::disk('public')->put($filepath, 'contents');

        DestroySupplierAction::run(Supplier::factory()->create(['image' => $filename]));

        Storage::disk('public')->assertExists($filepath);
    }
}
