<?php

namespace Tests\Feature\Console;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_deletes_accessory_images()
    {
        $accessory = Accessory::factory()->create(['image' => 'accessory-image.jpg']);
        $filepath = 'accessories/accessory-image.jpg';

        Storage::disk('public')->put($filepath, 'contents');

        $accessory->delete();
        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public function test_deletes_asset_images()
    {
        $asset = Asset::factory()->create(['image' => 'asset-image.jpg']);
        $filepath = 'assets/asset-image.jpg';

        Storage::disk('public')->put($filepath, 'contents');

        $asset->delete();
        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public function test_deletes_asset_model_images()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_category_images()
    {
        $category = Category::factory()->create(['image' => 'category-image.jpg']);

        $filepath = 'categories/category-image.jpg';

        Storage::disk('public')->put($filepath, 'contents');

        $category->delete();
        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public function test_deletes_manufacturer_images()
    {
        $this->markTestIncomplete();
    }

    private function firePurgeCommand()
    {
        $question = <<<TXT

****************************************************
THIS WILL PURGE ALL SOFT-DELETED ITEMS IN YOUR SYSTEM. 
There is NO undo. This WILL permanently destroy 
ALL of your deleted data. 
****************************************************

Do you wish to continue? No backsies! [y|N]
TXT;

        return $this->artisan('snipeit:purge')
            ->expectsConfirmation($question, 'yes');
    }
}
