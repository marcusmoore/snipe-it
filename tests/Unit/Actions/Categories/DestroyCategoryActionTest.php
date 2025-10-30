<?php

namespace Tests\Unit\Actions\Categories;

use App\Actions\Categories\DestroyCategoryAction;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DestroyCategoryActionTest extends TestCase
{
    public function test_soft_deletes_category()
    {
        $category = Category::factory()->create();

        DestroyCategoryAction::run($category);

        $this->assertSoftDeleted($category);
    }

    public function test_preserves_image_in_case_category_restored()
    {
        Storage::fake('public');

        $filename = 'temp-file.jpg';
        $filepath = 'categories/' . $filename;

        Storage::disk('public')->put($filepath, 'contents');

        DestroyCategoryAction::run(Category::factory()->create(['image' => $filename]));

        Storage::disk('public')->assertExists($filepath);
    }
}
