<?php

namespace Tests\Feature\Categories\Api;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteCategoriesTest extends TestCase implements TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $category = Category::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->deleteJson(route('api.categories.destroy', $category))
            ->assertForbidden();

        $this->assertNotSoftDeleted($category);
    }

    public function testCannotDeleteCategoryThatStillHasAssociatedAssets()
    {
        $asset = Asset::factory()->create();
        $category = $asset->model->category;

        $this->actingAsForApi(User::factory()->deleteCategories()->create())
            ->deleteJson(route('api.categories.destroy', $category))
            ->assertStatusMessageIs('error');

        $this->assertNotSoftDeleted($category);
    }

    public function testCannotDeleteCategoryThatStillHasAssociatedModels()
    {
        $model = AssetModel::factory()->create();
        $category = $model->category;

        $this->actingAsForApi(User::factory()->deleteCategories()->create())
            ->deleteJson(route('api.categories.destroy', $category))
            ->assertStatusMessageIs('error');

        $this->assertNotSoftDeleted($category);
    }

    public function testCanDeleteCategory()
    {
        $category = Category::factory()->create();

        $this->actingAsForApi(User::factory()->deleteCategories()->create())
            ->deleteJson(route('api.categories.destroy', $category))
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted($category);
    }

    public function test_preserves_image_in_case_category_restored()
    {
        Storage::fake('public');

        $category = Category::factory()->create(['image' => 'image.jpg']);

        Storage::disk('public')->put('categories/image.jpg', 'content');

        Storage::disk('public')->assertExists('categories/image.jpg');

        $this->actingAsForApi(User::factory()->deleteCategories()->create())
            ->deleteJson(route('api.categories.destroy', $category))
            ->assertStatusMessageIs('success');

        Storage::disk('public')->assertExists('categories/image.jpg');
    }
}
