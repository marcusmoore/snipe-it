<?php

namespace Tests\Feature\Console\Purge;

use App\Models\License;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeLicenseTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_licenses_purged()
    {
        $licenses = License::factory()->count(2)->create();

        $licenses->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('licenses', ['id' => $licenses->first()->id]);
        $this->assertDatabaseHas('licenses', ['id' => $licenses->last()->id]);
    }

    public function test_purges_license_seats_for_soft_deleted_license()
    {
        $this->markTestIncomplete();
    }

    public function test_associated_action_logs_are_not_purged_by_default()
    {
        $this->markTestIncomplete();
    }

    public function test_associated_action_logs_can_be_purged_via_env_variable()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_license_uploads()
    {
        $filepath = 'private_uploads/licenses';

        $licenses = License::factory()->count(2)->create();

        Storage::put("{$filepath}/to-remove.txt", 'contents');
        $licenses->first()->logUpload("to-remove.txt", '');

        Storage::put("{$filepath}/to-keep.txt", 'contents');
        $licenses->last()->logUpload("to-keep.txt", '');

        $licenses->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        Storage::assertMissing("{$filepath}/to-remove.txt");
        Storage::assertExists("{$filepath}/to-keep.txt");
    }
}
