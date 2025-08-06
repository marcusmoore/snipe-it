<?php

use App\Models\User;

test('requires permission', function () {
    $this->actingAsForApi(User::factory()->create())
        ->postJson(route('api.companies.store'))
        ->assertForbidden();
});

test('validation for creating company', function () {
    $this->actingAsForApi(User::factory()->createCompanies()->create())
        ->postJson(route('api.companies.store'))
        ->assertStatus(200)
        ->assertStatusMessageIs('error')
        ->assertJsonStructure([
            'messages' => [
                'name',
            ],
        ]);
});

test('can create company', function () {
    $this->actingAsForApi(User::factory()->createCompanies()->create())
        ->postJson(route('api.companies.store'), [
            'name' => 'My Cool Company',
            'notes' => 'A Cool Note',
        ])
        ->assertStatus(200)
        ->assertStatusMessageIs('success');

    $this->assertDatabaseHas('companies', [
        'name' => 'My Cool Company',
        'notes' => 'A Cool Note',
    ]);
});
