<?php

use App\Models\ReportTemplate;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\TestsPermissionsRequirement;

test('requires permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('report-templates.edit', ReportTemplate::factory()->create()))
        ->assertStatus(302);
});

test('cannot load edit page for another users report template', function () {
    $user = User::factory()->canViewReports()->create();
    $reportTemplate = ReportTemplate::factory()->create();

    $this->actingAs($user)
        ->get(route('report-templates.edit', $reportTemplate))
        ->assertStatus(302);
});

test('can load edit report template page', function () {
    $user = User::factory()->canViewReports()->create();
    $reportTemplate = ReportTemplate::factory()->for($user, 'creator')->create();

    $this->actingAs($user)
        ->get(route('report-templates.edit', $reportTemplate))
        ->assertOk();
});
