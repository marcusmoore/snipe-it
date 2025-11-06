<?php

namespace Tests\Feature\Console;

use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\Location;
use App\Models\Maintenance;
use App\Models\Manufacturer;
use App\Models\Statuslabel;
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

    public static function models_that_are_purged()
    {
        return [
            'Asset' => [Asset::class],
            'Accessory' => [Accessory::class],
            'AssetModel' => [AssetModel::class],
            'Category' => [Category::class],
            'Component' => [Component::class],
            'Consumable' => [Consumable::class],
            'License' => [License::class],
            'Location' => [Location::class],
            'Manufacturer' => [Manufacturer::class],
            'StatusLabel' => [Statuslabel::class],
            'Supplier' => [Supplier::class],
            'User' => [User::class],
        ];
    }

    #[DataProvider('models_that_are_purged')]
    public function test_purges_soft_deleted_models($modelClass)
    {
        // $this->markTestIncomplete();

        $model = $modelClass::factory()->create();

        $model->delete();

        $this->firePurgeCommand()->assertSuccessful();

        $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
    }

    public static function models_with_action_logs_that_are_purged()
    {
        return [
            'Asset' => [Asset::class],
            'Accessory' => [Accessory::class],
            'Component' => [Component::class],
            'Consumable' => [Consumable::class],
            'License' => [License::class],
            'User' => [User::class],
        ];
    }

    #[DataProvider('models_with_action_logs_that_are_purged')]
    public function test_purges_associated_action_logs()
    {
        $this->markTestIncomplete();

        $model = $modelClass::factory()->create();

        $actionLogEntries = Actionlog::whereMorphedTo('item', $model)->get();

        $this->assertGreaterThan(0, $actionLogEntries->count());

        $model->delete();

        $this->firePurgeCommand()->assertSuccessful();

        // todo assert action logs completely removed
    }

    public function test_purges_maintenances_for_soft_deleted_assets()
    {
        $this->markTestIncomplete();

        // create maintenance

        // delete its asset

        // fire command

        // ensure maintenance and associated logs are completely removed
    }

    public static function models_with_images()
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

    #[DataProvider('models_with_images')]
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

    public static function models_with_uploads()
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

    #[DataProvider('models_with_uploads')]
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
