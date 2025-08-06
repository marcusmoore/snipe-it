<?php

use App\Models\ReportTemplate;
use App\Models\User;

test('requires permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('report-templates.show', ReportTemplate::factory()->create()))
        ->assertStatus(302);
});

test('can load asaved report template', function () {
    $user = User::factory()->canViewReports()->create();
    $reportTemplate = ReportTemplate::factory()->make(['name' => 'My Awesome Template']);
    $user->reportTemplates()->save($reportTemplate);

    $this->actingAs($user)
        ->get(route('report-templates.show', $reportTemplate))
        ->assertOk()
        ->assertViewHas(['template' => function (ReportTemplate $templatePassedToView) use ($reportTemplate) {
            return $templatePassedToView->is($reportTemplate);
        }]);
});

test('cannot load another users saved report template', function () {
    $reportTemplate = ReportTemplate::factory()->create();

    $this->actingAs(User::factory()->canViewReports()->create())
        ->get(route('report-templates.show', $reportTemplate))
        ->assertStatus(302);
});
