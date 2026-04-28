<?php

namespace Tests\Feature\Reporting\Custom;

use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('custom-reporting')]
class CustomAccessoryReportTest extends TestCase
{
    public function test_requires_permission_to_view_page()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.custom.accessory'))
            ->assertForbidden();
    }

    public function test_requires_permission_to_run_report()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('reports.custom.accessory.run'), [
                //
            ])
            ->assertForbidden();
    }

    public function test_can_load_custom_report_page()
    {
        $this->actingAs(User::factory()->canViewReports()->create())
            ->get(route('reports.custom.accessory'))
            ->assertOk();
    }

    public function test_saved_templates_on_page_are_scoped_to_the_user_and_type()
    {
        $this->markTestIncomplete();
    }

    public function test_custom_accessory_report()
    {
        $this->markTestIncomplete();
    }

    public function test_custom_accessory_report_adheres_to_company_scoping()
    {
        $this->markTestIncomplete();
    }
}
