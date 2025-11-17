<?php

namespace Tests\Feature\Console\Purge;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('purging')]
class PurgeStatusLabelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_status_labels_purged()
    {
        $this->markTestIncomplete();
    }
}
