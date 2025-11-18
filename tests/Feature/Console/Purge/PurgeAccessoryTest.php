<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Accessory;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeAccessoryTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_accessories_purged()
    {
        $accessories = Accessory::factory()->count(2)->create();

        $accessories->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('accessories', ['id' => $accessories->first()->id]);
        $this->assertDatabaseHas('accessories', ['id' => $accessories->last()->id]);
    }

    public function test_associated_action_logs_are_not_purged_by_default()
    {
        $this->markTestIncomplete();
    }

    public function test_associated_action_logs_can_be_purged_via_env_variable()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_accessories_image()
    {
        $filename = str_random() . '.jpg';

        $accessory = Accessory::factory()->create(['image' => $filename]);

        $filepath = "accessories/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $accessory->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public function test_deletes_accessory_uploads()
    {
        $this->markTestIncomplete();
    }
}
