<?php

namespace Tests\Feature\Departments\Api;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Tests\Concerns\TestsMultipleFullCompanySupport;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteDepartmentTest extends TestCase implements TestsMultipleFullCompanySupport, TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $department = Department::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->deleteJson(route('api.departments.destroy', $department))
            ->assertForbidden();

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function testAdheresToMultipleFullCompanySupportScoping()
    {
        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $departmentA = Department::factory()->for($companyA)->create();
        $departmentB = Department::factory()->for($companyB)->create();
        $departmentC = Department::factory()->for($companyB)->create();

        $superUser = $companyA->users()->save(User::factory()->superuser()->make());
        $userInCompanyA = $companyA->users()->save(User::factory()->deleteDepartments()->make());
        $userInCompanyB = $companyB->users()->save(User::factory()->deleteDepartments()->make());

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($userInCompanyA)
            ->deleteJson(route('api.departments.destroy', $departmentB))
            ->assertStatusMessageIs('error');

        $this->actingAsForApi($userInCompanyB)
            ->deleteJson(route('api.departments.destroy', $departmentA))
            ->assertStatusMessageIs('error');

        $this->actingAsForApi($superUser)
            ->deleteJson(route('api.departments.destroy', $departmentC))
            ->assertStatusMessageIs('success');

        $this->assertNotNull($departmentA->fresh(), 'Department unexpectedly deleted');
        $this->assertNotNull($departmentB->fresh(), 'Department unexpectedly deleted');
        $this->assertNull($departmentC->fresh(), 'Department was not deleted');
    }

    public function testCannotDeleteDepartmentThatStillHasUsers()
    {
        $department = Department::factory()->hasUsers()->create();

        $this->actingAsForApi(User::factory()->deleteDepartments()->create())
            ->deleteJson(route('api.departments.destroy', $department))
            ->assertStatusMessageIs('error');

        $this->assertNotNull($department->fresh(), 'Department unexpectedly deleted');
    }

    public function testCanDeleteDepartment()
    {
        $department = Department::factory()->create();

        $this->actingAsForApi(User::factory()->deleteDepartments()->create())
            ->deleteJson(route('api.departments.destroy', $department))
            ->assertStatusMessageIs('success');

        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }
}