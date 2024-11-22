<?php

namespace Tests\Feature\Companies\Api;

use App\Models\User;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class CreateCompaniesTest extends TestCase implements TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $this->actingAsForApi(User::factory()->create())
            ->postJson(route('api.companies.store'))
            ->assertForbidden();
    }

    public function testRequiresValidData()
    {
        $this->actingAsForApi(User::factory()->createCompanies()->create())
            ->postJson(route('api.companies.store'), [
                //
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertMessagesContains('name');
    }

    public function testCanCreateCompany()
    {
        $user = User::factory()->createCompanies()->create();

        $this->actingAsForApi($user)
            ->postJson(route('api.companies.store'), [
                'name' => 'My Awesome Company',
                'fax' => '619-555-5555',
                'phone' => '619-666-6666',
                'email' => 'hi@awesome.co',
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('companies', [
            'name' => 'My Awesome Company',
            'fax' => '619-555-5555',
            'phone' => '619-666-6666',
            'email' => 'hi@awesome.co',
            'created_by' => $user->id,
        ]);
    }
}
