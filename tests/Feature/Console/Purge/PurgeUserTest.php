<?php

namespace Tests\Feature\Console\Purge;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeUserTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_users_purged()
    {
        $users = User::factory()->count(2)->create();

        $users->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $users->first()->id]);
        $this->assertDatabaseHas('users', ['id' => $users->last()->id]);
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
        $filename = str_random() . '.jpg';

        $accessory = User::factory()->create(['avatar' => $filename]);

        $filepath = "avatars/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $accessory->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public function test_deletes_user_uploads()
    {
        $this->markTestIncomplete();
    }
}
