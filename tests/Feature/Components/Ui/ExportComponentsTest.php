<?php

namespace Tests\Feature\Components\Ui;

use App\Models\Component;
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
        $this->actingAs(User::factory()->viewComponents()->create())
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

        $component = Component::factory()
            ->forCompany(['name' => 'Jedi Order'])
            ->forCategory(['name' => 'Lightsaber Parts'])
            ->forSupplier(['name' => 'Galaxy'])
            ->forManufacturer(['name' => 'Jedi'])
            ->forLocation(['name' => 'Space'])
            ->create([
                'name' => 'Kyber Crystal',
                'serial' => 'SN-12345',
                'model_number' => 'KC-001',
                'order_number' => '12345',
                'purchase_date' => '2026-05-13',
                'min_amt' => 5,
                'qty' => 12,
                'purchase_cost' => '999.99',
                'notes' => 'Rare...be careful',
            ]);

        $this->actingAs(User::factory()->viewComponents()->create())
            ->get(route('components.export'))
            ->assertOk()
            ->assertCsvHeader()
            ->assertSeePairsInStreamedResponse([
                'id' => (string) $component->id,
                'Company' => 'Jedi Order',
                'Name' => 'Kyber Crystal',
                'Serial' => 'SN-12345',
                'Category' => 'Lightsaber Parts',
                'Supplier' => 'Galaxy',
                'Model No.' => 'KC-001',
                'Manufacturer' => 'Jedi',
                'Location' => 'Space',
                'Order Number' => '12345',
                'Purchase Date' => '2026-05-13',
                'Min. Qty' => '5',
                'Total' => '10',
                'Remaining' => '12',
                'Unit Cost' => '999.99',
                'Notes' => 'Rare...be careful',
            ]);
    }

    public function test_adheres_to_full_multiple_company_support()
    {
        $this->markTestIncomplete();
    }
}
