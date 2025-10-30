<?php

namespace Tests\Feature\Console;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Component;
use App\Models\Manufacturer;
use App\Models\Supplier;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public static function models()
    {
        return [
            [Accessory::class, 'accessories'],
            [Asset::class, 'assets'],
            [AssetModel::class, 'models'],
            [Category::class, 'categories'],
            [Component::class, 'components'],
            [Manufacturer::class, 'manufacturers'],
            [Supplier::class, 'suppliers'],
        ];
    }

    #[DataProvider('models')]
    public function test_deletes_model_images($modelClass, $pathPrefix)
    {
        $filename = str_random() . '.jpg';

        $model = $modelClass::factory()->create(['image' => $filename]);

        $filepath = "{$pathPrefix}/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $model->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    private function firePurgeCommand()
    {
        $question = <<<TXT

****************************************************
THIS WILL PURGE ALL SOFT-DELETED ITEMS IN YOUR SYSTEM. 
There is NO undo. This WILL permanently destroy 
ALL of your deleted data. 
****************************************************

Do you wish to continue? No backsies! [y|N]
TXT;

        return $this->artisan('snipeit:purge')
            ->expectsConfirmation($question, 'yes');
    }
}
