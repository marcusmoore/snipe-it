<?php

use App\Models\ReportTemplate;
use App\Models\User;

test('requires permission', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('report-templates.update', ReportTemplate::factory()->create()))
        ->assertStatus(302);
});

test('cannot update another users report template', function () {
    $this->actingAs(User::factory()->canViewReports()->create())
        ->post(route('report-templates.update', ReportTemplate::factory()->create()))
        ->assertStatus(302);
});

test('updating report template requires valid fields', function () {
    $user = User::factory()->canViewReports()->create();

    $reportTemplate = ReportTemplate::factory()->for($user, 'creator')->create();

    $this->actingAs($user)
        ->post(route('report-templates.update', $reportTemplate), [
            //
        ])
        ->assertSessionHasErrors([
            'name' => 'The name field is required.',
        ]);
});

test('can update areport template', function () {
    $user = User::factory()->canViewReports()->create();

    $reportTemplate = ReportTemplate::factory()->for($user, 'creator')->create([
        'name' => 'Original Name',
        'options' => [
            'id' => 1,
            'category' => 1,
            'by_category_id' => 2,
            'company' => 1,
            'by_company_id' => [1, 2],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('report-templates.update', $reportTemplate), [
            'name' => 'Updated Name',
            'id' => 1,
            'company' => 1,
            'by_company_id' => [3],
        ]);

    $reportTemplate->refresh();
    expect($reportTemplate->name)->toEqual('Updated Name');
    expect($reportTemplate->checkmarkValue('id'))->toEqual(1);
    expect($reportTemplate->checkmarkValue('category'))->toEqual(0);
    expect($reportTemplate->selectValues('by_category_id'))->toEqual([]);
    expect($reportTemplate->checkmarkValue('company'))->toEqual(1);
    expect($reportTemplate->selectValues('by_company_id'))->toEqual([3]);
});
