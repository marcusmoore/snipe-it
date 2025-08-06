<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\License;
use App\Models\User;
use Tests\Support\ProvidesDataForFullMultipleCompanySupportTesting;

uses(ProvidesDataForFullMultipleCompanySupportTesting::class);

test('adheres to full multiple companies support scoping', function (User $actor, Company $company, Closure $assertions) {
    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAs($actor)
        ->post(route('licenses.store'), [
            'name' => 'My Cool License',
            'seats' => '1',
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'company_id' => $company->id,
        ]);

    $license = License::where('name', 'My Cool License')->sole();

    $assertions($license);
})->with('data for full multiple company support testing');
