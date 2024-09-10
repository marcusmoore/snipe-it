<?php

use App\Models\Company;
use App\Models\User;

test('a company can have users', function () {
    $company = Company::factory()->create();
    $user = User::factory()
            ->create(
                [
                    'company_id'=> $company->id
                ]
    );

    expect($company->users)->toHaveCount(1);
});
