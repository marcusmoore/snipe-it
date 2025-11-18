<?php

namespace Tests\Feature\Consumables\Ui;

use App\Models\Company;
use App\Models\Consumable;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteConsumableTest extends TestCase
{
    public function testRequiresPermissionToDeleteConsumable()
    {
        $this->actingAs(User::factory()->create())
            ->delete(route('consumables.destroy', Consumable::factory()->create()->id))
            ->assertForbidden();
    }

    public function testCannotDeleteConsumableFromAnotherCompany()
    {
        $this->settings->enableMultipleFullCompanySupport();

        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $consumableForCompanyA = Consumable::factory()->for($companyA)->create();
        $userForCompanyB = User::factory()->deleteConsumables()->for($companyB)->create();

        $this->actingAs($userForCompanyB)
            ->delete(route('consumables.destroy', $consumableForCompanyA->id))
            ->assertRedirect(route('consumables.index'));

        $this->assertNotSoftDeleted($consumableForCompanyA);
    }

    public function testCanDeleteConsumable()
    {
        $consumable = Consumable::factory()->create();

        $this->actingAs(User::factory()->deleteConsumables()->create())
            ->delete(route('consumables.destroy', $consumable->id))
            ->assertRedirect(route('consumables.index'));

        $this->assertSoftDeleted($consumable);
    }

    public function test_preserves_image_in_case_consumable_restored()
    {
        Storage::fake('public');

        $consumable = Consumable::factory()->create(['image' => 'image.jpg']);

        Storage::disk('public')->put('consumables/image.jpg', 'content');

        Storage::disk('public')->assertExists('consumables/image.jpg');

        $this->actingAs(User::factory()->deleteConsumables()->create())
            ->delete(route('consumables.destroy', $consumable->id))
            ->assertSessionHas('success');

        Storage::disk('public')->assertExists('consumables/image.jpg');

        $this->assertEquals('image.jpg', $consumable->fresh()->image);
    }

    public function test_preserves_uploads_in_case_consumable_restored()
    {
        $filepath = 'private_uploads/consumables';

        $consumable = Consumable::factory()->create();

        Storage::put("{$filepath}/to-keep.txt", 'contents');
        $consumable->logUpload("to-keep.txt", '');

        $this->actingAs(User::factory()->deleteConsumables()->create())
            ->delete(route('consumables.destroy', $consumable->id))
            ->assertSessionHas('success');

        Storage::assertExists("{$filepath}/to-keep.txt");
    }
}
