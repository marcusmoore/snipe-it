<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Component;
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
        $this->markTestIncomplete();

        $components = Component::factory()->count(2)->create();

        $components->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('components', ['id' => $components->first()->id]);
        $this->assertDatabaseHas('components', ['id' => $components->last()->id]);
    }

    public function test_associated_action_logs_are_not_purged_by_default()
    {
        $this->markTestIncomplete();
    }

    public function test_associated_action_logs_can_be_purged_via_env_variable()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_components_image()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_component_uploads()
    {
        $this->markTestIncomplete();
    }
}
