<?php

use App\Models\Accessory;
use App\Models\Category;
use PHPUnit\Framework\Attributes\DataProvider;

uses(\Tests\Support\ProvidesDataForFullMultipleCompanySupportTesting::class);

test('adheres to full multiple companies support scoping', function ($data) {
    ['actor' => $actor, 'company_attempting_to_associate' => $company, 'assertions' => $assertions] = $data();

    $this->settings->enableMultipleFullCompanySupport();

    $this->actingAs($actor)
        ->post(route('accessories.store'), [
            'redirect_option' => 'index',
            'name' => 'My Cool Accessory',
            'qty' => '1',
            'category_id' => Category::factory()->create()->id,
            'company_id' => $company->id,
        ]);

    $accessory = Accessory::withoutGlobalScopes()->where([
        'name' => 'My Cool Accessory',
    ])->sole();

    $assertions($accessory);
})->with('dataForFullMultipleCompanySupportTesting');