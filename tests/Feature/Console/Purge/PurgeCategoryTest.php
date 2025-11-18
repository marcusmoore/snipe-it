<?php

namespace Tests\Feature\Console\Purge;

use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Console\Purge\Traits\FiresPurgeCommand;
use Tests\TestCase;

#[Group('purging')]
class PurgeCategoryTest extends TestCase
{
    use FiresPurgeCommand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function test_soft_deleted_categories_purged()
    {
        $categories = Category::factory()->count(2)->create();

        $categories->first()->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing('categories', ['id' => $categories->first()->id]);
        $this->assertDatabaseHas('categories', ['id' => $categories->last()->id]);
    }

    public function test_deletes_categories_image()
    {
        $filename = str_random() . '.jpg';

        $category = Category::factory()->create(['image' => $filename]);

        $filepath = "categories/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $category->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }
}
