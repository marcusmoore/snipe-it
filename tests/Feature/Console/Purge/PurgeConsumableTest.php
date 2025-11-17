<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Consumable;
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
        $this->markTestIncomplete();

        $consumables = Consumable::factory()->count(2)->create();

        $consumables->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('consumables', ['id' => $consumables->first()->id]);
        $this->assertDatabaseHas('consumables', ['id' => $consumables->last()->id]);
    }

    public function test_associated_action_logs_are_not_purged_by_default()
    {
        $this->markTestIncomplete();
    }

    public function test_associated_action_logs_can_be_purged_via_env_variable()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_consumables_image()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_consumable_uploads()
    {
        $this->markTestIncomplete();
    }
}
