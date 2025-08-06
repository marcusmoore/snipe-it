<?php

use App\Models\ReportTemplate;
use App\Models\User;

test('requires permission', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('report-templates.store'))
        ->assertForbidden();
});

test('saving report template requires valid fields', function () {
    $this->actingAs(User::factory()->canViewReports()->create())
        ->post(route('report-templates.store'), [
            'name' => '',
        ])
        ->assertSessionHasErrors('name');
});

test('redirecting after validation error restores inputs', function () {
    $this->actingAs(User::factory()->canViewReports()->create())
        // start on the custom report page
        ->from(route('reports/custom'))
        ->followingRedirects()
        ->post(route('report-templates.store'), [
            'name' => '',
            // set some values to ensure they are still present
            // when returning to the custom report page.
            'by_company_id' => [2, 3]
        ])->assertViewHas(['template' => function (ReportTemplate $reportTemplate) {
            return data_get($reportTemplate, 'options.by_company_id') === [2, 3];
        }]);
});

test('can save areport template', function () {
    $user = User::factory()->canViewReports()->create();

    $this->actingAs($user)
        ->post(route('report-templates.store'), [
            'name' => 'My Awesome Template',
            'company' => '1',
            'by_company_id' => ['1', '2'],
        ])
        ->assertRedirect();

    $template = $user->reportTemplates->first(function ($report) {
        return $report->name === 'My Awesome Template';
    });

    expect($template)->not->toBeNull();
    expect($template->options['company'])->toEqual('1');
    expect($template->options['by_company_id'])->toEqual(['1', '2']);
});
