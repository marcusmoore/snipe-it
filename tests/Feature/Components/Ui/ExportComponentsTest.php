<?php

namespace Tests\Feature\Components\Ui;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class ExportComponentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // todo: remove
        Model::preventLazyLoading();
    }

    public function test_requires_permission()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('components.export'))
            ->assertForbidden();
    }

    public function test_component_export_headers()
    {
        $this->markTestIncomplete();

        $this->actingAs(User::factory()->canViewReports()->create())
            ->get(route('components.export'))
            ->assertOk()
            ->assertCsvHeader()
            ->assertSeeTextInStreamedResponse([
                trans('general.id'),
                trans('general.company'),
                trans('general.name'),
                trans('admin/hardware/form.serial'),
                trans('general.category'),
                trans('general.supplier'),
                trans('admin/models/table.modelnumber'),
                trans('general.manufacturer'),
                trans('general.location'),
                trans('general.order_number'),
                trans('general.purchase_date'),
                trans('general.min_amt'),
                trans('admin/components/general.total'),
                trans('admin/components/general.remaining'),
                trans('general.unit_cost'),
                trans('general.notes'),
                trans('general.created_at'),
                trans('general.updated_at'),
            ]);
    }

    public function test_can_export_components_to_csv()
    {
        $this->markTestIncomplete();
    }

    public function test_adheres_to_full_multiple_company_support()
    {
        $this->markTestIncomplete();
    }
}
