<?php

use App\Models\ReportTemplate;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\TestsPermissionsRequirement;

test('requires permission', function () {
    $reportTemplate = ReportTemplate::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('report-templates.destroy', $reportTemplate->id))
        ->assertStatus(302);

    $this->assertModelExists($reportTemplate);
});

test('cannot delete another users report template', function () {
    $reportTemplate = ReportTemplate::factory()->create();

    $this->actingAs(User::factory()->canViewReports()->create())
        ->delete(route('report-templates.destroy', $reportTemplate->id))
        ->assertStatus(302);

    $this->assertModelExists($reportTemplate);
});

test('can delete areport template', function () {
    $user = User::factory()->canViewReports()->create();
    $reportTemplate = ReportTemplate::factory()->for($user, 'creator')->create();

    $this->actingAs($user)
        ->delete(route('report-templates.destroy', $reportTemplate->id))
        ->assertRedirect(route('reports/custom'));

    $this->assertSoftDeleted($reportTemplate);
});
