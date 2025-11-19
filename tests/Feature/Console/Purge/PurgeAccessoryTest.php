<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Accessory;
use Illuminate\Support\Facades\DB;
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
        // $this->markTestIncomplete();

        $accessory = Accessory::factory()->create();

        $originalCount = DB::table('action_logs')
            ->where([
                'item_type' => Accessory::class,
                'item_id' => $accessory->id,
            ])
            ->count();

        $this->assertGreaterThan(
            0,
            $originalCount,
            'Model does not have action log entries as expected'
        );

        $accessory->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $newCount = DB::table('action_logs')
            ->where([
                'item_type' => Accessory::class,
                'item_id' => $accessory->id,
            ])
            ->whereNull('deleted_at')
            ->count();

        // all entries should be soft deleted including the last "deleted" entry
        $this->assertEquals($originalCount + 1, $newCount);
    }

    public function test_associated_action_logs_can_be_purged_via_env_variable()
    {
        // $this->markTestIncomplete();

        // todo: build out this variable
        config(['app.include_action_logs_when_purging', true]);

        $accessory = Accessory::factory()->create();

        $originalCount = DB::table('action_logs')
            ->where([
                'item_type' => Accessory::class,
                'item_id' => $accessory->id,
            ])
            ->count();

        $this->assertGreaterThan(
            0,
            $originalCount,
            'Model does not have action log entries as expected'
        );

        $accessory->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $newCount = DB::table('action_logs')
            ->where([
                'item_type' => Accessory::class,
                'item_id' => $accessory->id,
            ])
            ->count();

        // todo: should this only be the final "delete" or nothing at all?
        // Current behavior is one "delete" entry
        $this->assertEquals($originalCount + 1, $newCount);
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
        $filepath = 'private_uploads/accessories';

        $accessories = Accessory::factory()->count(2)->create();

        Storage::put("{$filepath}/to-remove.txt", 'contents');
        $accessories->first()->logUpload("to-remove.txt", '');

        Storage::put("{$filepath}/to-keep.txt", 'contents');
        $accessories->last()->logUpload("to-keep.txt", '');

        $accessories->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        Storage::assertMissing("{$filepath}/to-remove.txt");
        Storage::assertExists("{$filepath}/to-keep.txt");
    }
}
