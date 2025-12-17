<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Consumable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeConsumableTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_consumables_purged()
    {
        $consumables = Consumable::factory()->count(2)->create();

        $consumables->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('consumables', ['id' => $consumables->first()->id]);
        $this->assertDatabaseHas('consumables', ['id' => $consumables->last()->id]);
    }

    public function test_associated_action_logs_are_not_purged_by_default()
    {
        $consumable = Consumable::factory()->create();

        $query = DB::table('action_logs')->where([
            'item_type' => Consumable::class,
            'item_id' => $consumable->id,
        ]);

        $originalCount = $query->count();

        $this->assertGreaterThan(
            0,
            $originalCount,
            'Model does not have action log entries as expected'
        );

        $consumable->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $newCount = $query
            ->whereNotNull('deleted_at')
            ->count();

        // all entries should be soft deleted including the "delete" and "force delete" entries
        $this->assertEquals($originalCount + 2, $newCount);
    }

    public function test_associated_action_logs_can_be_purged_via_env_variable()
    {
        Config::set('app.include_related_action_logs_when_purging', true);

        $consumable = Consumable::factory()->create();

        $query = DB::table('action_logs')->where([
            'item_type' => Consumable::class,
            'item_id' => $consumable->id,
        ]);

        $originalCount = $query->count();

        $this->assertGreaterThan(
            0,
            $originalCount,
            'Model does not have action log entries as expected'
        );

        $consumable->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $newCount = $query->count();

        $this->assertEquals(0, $newCount);
    }

    public function test_deletes_consumables_image()
    {
        $filename = str_random() . '.jpg';

        $consumable = Consumable::factory()->create(['image' => $filename]);

        $filepath = "consumables/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $consumable->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public function test_deletes_consumable_uploads()
    {
        $filepath = 'private_uploads/consumables';

        $consumables = Consumable::factory()->count(2)->create();

        Storage::put("{$filepath}/to-remove.txt", 'contents');
        $consumables->first()->logUpload("to-remove.txt", '');

        Storage::put("{$filepath}/to-keep.txt", 'contents');
        $consumables->last()->logUpload("to-keep.txt", '');

        $consumables->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        Storage::assertMissing("{$filepath}/to-remove.txt");
        Storage::assertExists("{$filepath}/to-keep.txt");
    }
}
