<?php

namespace Tests\Feature\Console\Purge;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('purging')]
class PurgeSupplierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_suppliers_purged()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_suppliers_image()
    {
        $this->markTestIncomplete();
    }
}
