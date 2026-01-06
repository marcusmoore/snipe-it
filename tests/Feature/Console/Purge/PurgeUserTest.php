<?php

namespace Tests\Feature\Console\Purge;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
        $user = User::factory()->create();

        $query = DB::table('action_logs')->where([
            'item_type' => User::class,
            'item_id' => $user->id,
        ]);

        $originalCount = $query->whereNull('deleted_at')->count();

        $this->assertGreaterThan(
            0,
            $originalCount,
            'Model does not have action log entries as expected'
        );

        $user->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $newCount = $query->whereNotNull('deleted_at')->count();

        // all entries should be soft deleted including the "delete" and "force delete" entries
        $this->assertEquals($originalCount + 2, $newCount);
    }

    public function test_associated_action_logs_can_be_purged_via_env_variable()
    {
        Config::set('app.include_related_action_logs_when_purging', true);

        $user = User::factory()->create();

        $query = DB::table('action_logs')->where([
            'item_type' => User::class,
            'item_id' => $user->id,
        ]);

        $originalCount = $query->count();

        $this->assertGreaterThan(
            0,
            $originalCount,
            'Model does not have action log entries as expected'
        );

        $user->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $newCount = $query->count();

        $this->assertEquals(0, $newCount);
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
        $filepath = 'private_uploads/users';

        $users = User::factory()->count(2)->create();

        Storage::put("{$filepath}/to-remove.txt", 'contents');
        $users->first()->logUpload("to-remove.txt", '');

        Storage::put("{$filepath}/to-keep.txt", 'contents');
        $users->last()->logUpload("to-keep.txt", '');

        $users->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        Storage::assertMissing("{$filepath}/to-remove.txt");
        Storage::assertExists("{$filepath}/to-keep.txt");
    }
}
