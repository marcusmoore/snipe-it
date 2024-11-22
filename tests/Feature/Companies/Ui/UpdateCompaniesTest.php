<?php

namespace Tests\Feature\Companies\Ui;

use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class UpdateCompaniesTest extends TestCase
{
    public function testRequiresPermissionToViewCompanyUpdatePage()
    {
        $company = Company::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('companies.edit', $company->id))
            ->assertForbidden();
    }

    public function testCannotLoadUpdatePageForNonExistentCompany()
    {
        $this->markTestIncomplete();

        $this->actingAs(User::factory()->editCompanies()->create())
            ->get(route('companies.edit', 999))
            ->assertNotFound();
    }

    public function testCannotViewUpdatePageForAnotherCompany()
    {
        $this->markTestIncomplete();

        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $user = User::factory()->for($companyA)->editCompanies()->create();

        $this->actingAs($user)
            ->get(route('companies.edit', $companyB->id))
            ->assertForbidden();
    }

    public function testCompanyUpdatePageRenders()
    {
        $this->markTestIncomplete();
    }

    public function testValidDataRequiredToUpdateCompany()
    {
        $this->markTestIncomplete();
    }

    public function testRequiresPermissionToUpdateCompany()
    {
        $this->markTestIncomplete();
    }

    public function testCanUpdateCompany()
    {
        $this->markTestIncomplete();
    }
}
