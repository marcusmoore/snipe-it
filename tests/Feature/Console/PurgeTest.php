<?php

namespace Tests\Feature\Console;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumable;
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

    public static function modelsWithImages()
    {
        return [
            'Accessory' => [Accessory::class, 'accessories'],
            'Asset' => [Asset::class, 'assets'],
            'AssetModel' => [AssetModel::class, 'models'],
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

    public function test_deletes_users_uploads()
    {
        $pathPrefix = 'private_uploads/users';

        $filename = str_random() . '.jpg';

        $filepath = "{$pathPrefix}/{$filename}";

        $user = User::factory()->create();

        Storage::put($filepath, 'contents');

        $user->logUpload($filename, '');

        $user->delete();

        Storage::assertExists($filepath);

        $this->firePurgeCommand()->assertSuccessful();

        Storage::assertMissing($filepath);
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
