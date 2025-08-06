<?php

use App\Models\Company;
use App\Models\User;

test('page renders', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('companies.edit', Company::factory()->create()))
        ->assertOk();
});
