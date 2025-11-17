<?php

namespace Tests\Feature\Console\Purge;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('purging')]
class PurgeManufacturerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_manufacturers_purged()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_manufacturers_image()
    {
        $this->markTestIncomplete();
    }
}
