<?php

namespace Tests\Feature\Users\Ui;

use App\Models\User;
use Tests\TestCase;

class RestoreUserTest extends TestCase
{
    public function test_permission_needed_to_restore_user()
    {
        $trashedUser = User::factory()->trashed()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('users.restore.store', ['userId' => $trashedUser->id]))
            ->assertForbidden();
    }

    public function test_cannot_restore_non_deleted_user()
    {
        $nonTrashedUser = User::factory()->create();

        $this->actingAs(User::factory()->deleteUsers()->create())
            ->post(route('users.restore.store', ['userId' => $nonTrashedUser->id]))
            ->assertSessionHas('error');
    }

    public function test_can_restore_user()
    {
        $this->markTestIncomplete();
    }

    public function test_restoring_user_does_not_restore_pending_checkout_acceptances()
    {
        $this->markTestIncomplete();
    }
}
