<?php

namespace Tests\Feature\Consumables\Api;

use App\Models\Actionlog;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\TestsFullMultipleCompaniesSupport;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteConsumablesTest extends TestCase implements TestsFullMultipleCompaniesSupport, TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $consumable = Consumable::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->deleteJson(route('api.consumables.destroy', $consumable))
            ->assertForbidden();

        $this->assertNotSoftDeleted($consumable);
    }

    public function testAdheresToFullMultipleCompaniesSupportScoping()
    {
        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $consumableA = Consumable::factory()->for($companyA)->create();
        $consumableB = Consumable::factory()->for($companyB)->create();
        $consumableC = Consumable::factory()->for($companyB)->create();

        $superUser = $companyA->users()->save(User::factory()->superuser()->make());
        $userInCompanyA = $companyA->users()->save(User::factory()->deleteConsumables()->make());
        $userInCompanyB = $companyB->users()->save(User::factory()->deleteConsumables()->make());

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($userInCompanyA)
            ->deleteJson(route('api.consumables.destroy', $consumableB))
            ->assertStatusMessageIs('error');

        $this->actingAsForApi($userInCompanyB)
            ->deleteJson(route('api.consumables.destroy', $consumableA))
            ->assertStatusMessageIs('error');

        $this->actingAsForApi($superUser)
            ->deleteJson(route('api.consumables.destroy', $consumableC))
            ->assertStatusMessageIs('success');

        $this->assertNotSoftDeleted($consumableA);
        $this->assertNotSoftDeleted($consumableB);
        $this->assertSoftDeleted($consumableC);
    }

    public function testCanDeleteConsumables()
    {
        $consumable = Consumable::factory()->create();

        $this->actingAsForApi(User::factory()->deleteConsumables()->create())
            ->deleteJson(route('api.consumables.destroy', $consumable))
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted($consumable);
    }

    public function test_preserves_image_in_case_consumable_restored()
    {
        Storage::fake('public');

        $consumable = Consumable::factory()->create(['image' => 'image.jpg']);

        Storage::disk('public')->put('consumables/image.jpg', 'content');

        Storage::disk('public')->assertExists('consumables/image.jpg');

        $this->actingAsForApi(User::factory()->deleteConsumables()->create())
            ->deleteJson(route('api.consumables.destroy', $consumable))
            ->assertStatusMessageIs('success');

        Storage::disk('public')->assertExists('consumables/image.jpg');

        $this->assertEquals('image.jpg', $consumable->fresh()->image);
    }

    public function test_preserves_uploads_in_case_consumable_restored()
    {
        $filepath = 'private_uploads/consumables';

        $consumable = Consumable::factory()->create();

        Storage::put("{$filepath}/to-keep.txt", 'contents');
        $consumable->logUpload("to-keep.txt", '');

        $this->actingAsForApi(User::factory()->deleteConsumables()->create())
            ->deleteJson(route('api.consumables.destroy', $consumable))
            ->assertStatusMessageIs('success');

        Storage::assertExists("{$filepath}/to-keep.txt");
    }
}
