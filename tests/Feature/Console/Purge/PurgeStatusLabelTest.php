<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Statuslabel;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeStatusLabelTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_status_labels_purged()
    {
        $statusLabels = Statuslabel::factory()->count(2)->create();

        $statusLabels->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('status_labels', ['id' => $statusLabels->first()->id]);
        $this->assertDatabaseHas('status_labels', ['id' => $statusLabels->last()->id]);
    }
}
