<?php

namespace Tests\Feature\Console;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('focus')]
class PurgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
    }

    public function testPurgesModels()
    {
        $this->markTestIncomplete();

        // Assets
        // Maintenance
        // Accessories
        // AssetModels
        // Categories
        // Components
        // Consumables
        // Licenses
        // Locations
        // Manufacturers
        // StatusLabels
        // Suppliers
        // Users
    }

    public static function modelsWithImages()
    {
        return [
            'Accessory' => [Accessory::class, 'accessories'],
            'Asset' => [Asset::class, 'assets'],
            'Asset Model' => [AssetModel::class, 'models'],
            'Category' => [Category::class, 'categories'],
            'Component' => [Component::class, 'components'],
            'Consumable' => [Consumable::class, 'consumables'],
            'Manufacturer' => [Manufacturer::class, 'manufacturers'],
            'Location' => [Location::class, 'locations'],
            'Supplier' => [Supplier::class, 'suppliers'],
            'User' => [User::class, 'avatars', 'avatar'],
        ];
    }

    #[DataProvider('modelsWithImages')]
    public function test_deletes_model_images($modelClass, $pathPrefix, $property = 'image')
    {
        $filename = str_random() . '.jpg';

        $model = $modelClass::factory()->create([$property => $filename]);

        $filepath = "{$pathPrefix}/{$filename}";

        Storage::disk('public')->put($filepath, 'contents');

        $model->delete();

        Storage::disk('public')->assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::disk('public')->assertMissing($filepath);
    }

    public static function modelsWithUploads()
    {
        return [
            'Accessory' => [Accessory::class, 'private_uploads/accessories'],
            'Asset' => [Asset::class, 'private_uploads/assets'],
            'Asset Model' => [AssetModel::class, 'private_uploads/models'],
            'Component' => [Component::class, 'private_uploads/components'],
            'Consumable' => [Consumable::class, 'private_uploads/consumables'],
            'License' => [License::class, 'private_uploads/licenses'],
            'Location' => [Location::class, 'private_uploads/locations'],
            'User' => [User::class, 'private_uploads/users'],
        ];
    }

    #[DataProvider('modelsWithUploads')]
    public function test_deletes_uploads($modelClass, $pathPrefix)
    {
        $filename = str_random() . '.jpg';

        $filepath = "{$pathPrefix}/{$filename}";

        $model = $modelClass::factory()->create();

        Storage::put($filepath, 'contents');

        $model->logUpload($filename, '');

        $this->addUploadForAnotherModel($modelClass, $pathPrefix, 'keep.jpg');

        $model->delete();

        Storage::assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::assertMissing($filepath);
        $this->assertUploadRemainsForModel($pathPrefix, 'keep.jpg');
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

    private function addUploadForAnotherModel($modelClass, string $pathPrefix, string $filename): void
    {
        Storage::put("{$pathPrefix}/{$filename}", 'contents');

        $modelClass::factory()->create()->logUpload($filename, '');

        Storage::assertExists("{$pathPrefix}/{$filename}");
    }

    private function assertUploadRemainsForModel($pathPrefix, string $filename)
    {
        Storage::assertExists("{$pathPrefix}/{$filename}");
    }
}
