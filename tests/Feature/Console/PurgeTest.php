<?php

namespace Tests\Feature\Console;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('purging')]
class PurgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::fake('public');
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
