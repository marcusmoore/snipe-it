<?php

namespace Tests\Feature\Console\Purge;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('purging')]
class PurgeLocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_locations_purged()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_locations_image()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_location_uploads()
    {
        $this->markTestIncomplete();
    }
}
