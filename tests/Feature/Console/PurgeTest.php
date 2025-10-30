<?php

namespace Tests\Feature\Console;

use App\Models\Accessory;
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
        // create an accessory
        $accessory = Accessory::factory()->create(['image' => 'temp-file.jpg']);
        $filepath = 'accessories/temp-file.jpg';

        // store image for accessory
        Storage::disk('public')->put($filepath, 'contents');

        // soft-delete accessory
        $accessory->delete();

        // run purge
        $this->firePurgeCommand()->assertSuccessful();

        // assert image removed
        Storage::disk('public')->assertMissing($filepath);

    }

    public function test_deletes_asset_images()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_asset_model_images()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_category_images()
    {
        $this->markTestIncomplete();
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
