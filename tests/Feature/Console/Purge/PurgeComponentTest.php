<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Component;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeComponentTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_components_purged()
    {
        $components = Component::factory()->count(2)->create();

        $components->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('components', ['id' => $components->first()->id]);
        $this->assertDatabaseHas('components', ['id' => $components->last()->id]);
    }

    public function test_associated_action_logs_are_not_purged_by_default()
    {
        $component = Component::factory()->create();

        $query = DB::table('action_logs')->where([
            'item_type' => Component::class,
            'item_id' => $component->id,
        ]);

        $originalCount = $query->count();

        $this->assertGreaterThan(
            0,
            $originalCount,
            'Model does not have action log entries as expected'
        );

        $component->delete();

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

        $component = Component::factory()->create();

        $query = DB::table('action_logs')->where([
            'item_type' => Component::class,
            'item_id' => $component->id,
        ]);

        $originalCount = $query->count();

        $this->assertGreaterThan(
            0,
            $originalCount,
            'Model does not have action log entries as expected'
        );

        $component->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $newCount = $query->count();

        $this->assertEquals(0, $newCount);
    }

    public function test_deletes_components_image()
    {
        $filename = str_random() . '.jpg';

        $component = Component::factory()->create(['image' => $filename]);

        $filepath = "components/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $component->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public function test_deletes_component_uploads()
    {
        $filepath = 'private_uploads/components';

        $components = Component::factory()->count(2)->create();

        Storage::put("{$filepath}/to-remove.txt", 'contents');
        $components->first()->logUpload("to-remove.txt", '');

        Storage::put("{$filepath}/to-keep.txt", 'contents');
        $components->last()->logUpload("to-keep.txt", '');

        $components->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        Storage::assertMissing("{$filepath}/to-remove.txt");
        Storage::assertExists("{$filepath}/to-keep.txt");
    }
}
