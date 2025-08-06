<?php

use App\Models\Group;
use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('groups.edit', Group::factory()->create()->id))
        ->assertOk();
});

test('user can edit groups', function () {
    $group = Group::factory()->create(['name' => 'Test Group']);
    expect(Group::where('name', 'Test Group')->exists())->toBeTrue();

    $response = $this->actingAs(User::factory()->superuser()->create())
        ->put(route('groups.update', ['group' => $group]), [
            'name' => 'Test Group Edited',
            'notes' => 'Test Note Edited',
        ])
        ->assertStatus(302)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('groups.index'));

    $this->followRedirects($response)->assertSee('Success');
    expect(Group::where('name', 'Test Group Edited')->where('notes', 'Test Note Edited')->exists())->toBeTrue();
});
