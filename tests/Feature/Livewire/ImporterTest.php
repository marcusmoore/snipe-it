<?php

use App\Livewire\Importer;
use App\Models\User;
use Livewire\Livewire;

test('renders successfully', function () {
    Livewire::actingAs(User::factory()->canImport()->create())
        ->test(Importer::class)
        ->assertStatus(200);
});

test('requires permission', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Importer::class)
        ->assertStatus(403);
});
