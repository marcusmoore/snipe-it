<?php

namespace Tests\Feature\Console\Purge;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('purging')]
class PurgeUserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_users_purged()
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

    public function test_deletes_users_avatar()
    {
        $this->markTestIncomplete();
    }

    public function test_deletes_user_uploads()
    {
        $this->markTestIncomplete();
    }
}
