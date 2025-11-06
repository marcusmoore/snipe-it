<?php

namespace App\Console\Commands;

use App\Actions\DeleteFile;
use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Statuslabel;
use App\Models\Supplier;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Purge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snipeit:purge {--force=false}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge all soft-deleted deleted records in the database. This will rewrite history for items that have been edited, or checked in or out. It will also rewrite history for users associated with deleted items.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $force = $this->option('force');
        if (($this->confirm("\n****************************************************\nTHIS WILL PURGE ALL SOFT-DELETED ITEMS IN YOUR SYSTEM. \nThere is NO undo. This WILL permanently destroy \nALL of your deleted data. \n****************************************************\n\nDo you wish to continue? No backsies! [y|N]")) || $force == 'true') {

            $this->purgeAssets();

            $this->purgeLocations();

            $this->purgeAccessories();

            $this->purgeConsumables();

            $this->purgeComponents();

            $this->purgeLicenses();

            $this->purgeAssetModels();

            $this->purgeCategories();

            $this->purgeSuppliers();

            $this->purgeUsers();

            $this->purgeManufacturers();

            $this->purgeStatusLabels();
        } else {
            $this->info('Action canceled. Nothing was purged.');
        }
    }

    private function purgeAccessories(): void
    {
        $accessories = Accessory::whereNotNull('deleted_at')->with('uploads')->withTrashed()->get();

        $accessory_assoc = 0;
        $this->info($accessories->count() . ' accessories purged.');
        foreach ($accessories as $accessory) {
            $accessory->uploads->pluck('filename')->each(function ($filename) {
                try {
                    DeleteFile::run('private_uploads/accessories' . '/' . $filename);
                } catch (Exception $e) {
                    Log::info('An error occurred while deleting files: ' . $e->getMessage());
                }
            });

            $this->info('- Accessory "' . $accessory->name . '" deleted.');
            $accessory_assoc += $accessory->assetlog()->count();
            $accessory->assetlog()->forceDelete();
            $accessory->forceDelete();
            DeleteFile::run('accessories/' . $accessory->image, 'public');
        }
        $this->info($accessory_assoc . ' corresponding log records purged.');
    }

    private function purgeAssets(): void
    {
        $assets = Asset::whereNotNull('deleted_at')->with('uploads')->withTrashed()->get();
        $assetcount = $assets->count();
        $this->info($assets->count() . ' assets purged.');
        $asset_assoc = 0;
        $maintenances = 0;

        foreach ($assets as $asset) {
            $asset->uploads->pluck('filename')->each(function ($filename) {
                try {
                    DeleteFile::run('private_uploads/assets' . '/' . $filename);
                } catch (Exception $e) {
                    Log::info('An error occurred while deleting files: ' . $e->getMessage());
                }
            });

            $this->info('- Asset "' . $asset->display_name . '" deleted.');
            $asset_assoc += $asset->assetlog()->count();
            $asset->assetlog()->forceDelete();
            $maintenances += $asset->maintenances()->count();
            $asset->maintenances()->forceDelete();
            $asset->forceDelete();
            DeleteFile::run('assets/' . $asset->image, 'public');
        }

        $this->info($asset_assoc . ' corresponding log records purged.');
        $this->info($maintenances . ' corresponding maintenance records purged.');
    }

    private function purgeAssetModels(): void
    {
        $models = AssetModel::whereNotNull('deleted_at')->with('uploads')->withTrashed()->get();
        $this->info($models->count() . ' asset models purged.');
        foreach ($models as $model) {
            $model->uploads->pluck('filename')->each(function ($filename) {
                try {
                    DeleteFile::run('private_uploads/models' . '/' . $filename);
                } catch (Exception $e) {
                    Log::info('An error occurred while deleting files: ' . $e->getMessage());
                }
            });

            $this->info('- Asset Model "' . $model->name . '" deleted.');
            $model->forceDelete();
            DeleteFile::run('models/' . $model->image, 'public');
        }
    }

    private function purgeCategories(): void
    {
        $categories = Category::whereNotNull('deleted_at')->withTrashed()->get();
        $this->info($categories->count() . ' categories purged.');
        foreach ($categories as $category) {
            $this->info('- Category "' . $category->name . '" deleted.');
            $category->forceDelete();
            DeleteFile::run('categories' . '/' . $category->image, 'public');
        }
    }

    private function purgeComponents(): void
    {
        $components = Component::whereNotNull('deleted_at')->with('uploads')->withTrashed()->get();

        $this->info($components->count() . ' components purged.');
        foreach ($components as $component) {
            $component->uploads->pluck('filename')->each(function ($filename) {
                try {
                    DeleteFile::run('private_uploads/components' . '/' . $filename);
                } catch (Exception $e) {
                    Log::info('An error occurred while deleting files: ' . $e->getMessage());
                }
            });

            $this->info('- Component "' . $component->name . '" deleted.');
            $component->assetlog()->forceDelete();
            $component->forceDelete();
            DeleteFile::run('components/' . $component->image, 'public');
        }
    }

    private function purgeConsumables(): void
    {
        $consumables = Consumable::whereNotNull('deleted_at')->with('uploads')->withTrashed()->get();

        $this->info($consumables->count() . ' consumables purged.');
        foreach ($consumables as $consumable) {
            $consumable->uploads->pluck('filename')->each(function ($filename) {
                try {
                    DeleteFile::run('private_uploads/consumables' . '/' . $filename);
                } catch (Exception $e) {
                    Log::info('An error occurred while deleting files: ' . $e->getMessage());
                }
            });

            $this->info('- Consumable "' . $consumable->name . '" deleted.');
            $consumable->assetlog()->forceDelete();
            $consumable->forceDelete();
            DeleteFile::run('consumables/' . $consumable->image, 'public');
        }
    }

    private function purgeLicenses(): void
    {
        $licenses = License::whereNotNull('deleted_at')->with('uploads')->withTrashed()->get();

        $this->info($licenses->count() . ' licenses purged.');
        foreach ($licenses as $license) {
            $license->uploads->pluck('filename')->each(function ($filename) {
                try {
                    DeleteFile::run('private_uploads/licenses' . '/' . $filename);
                } catch (Exception $e) {
                    Log::info('An error occurred while deleting files: ' . $e->getMessage());
                }
            });
            $this->info('- License "' . $license->name . '" deleted.');
            $license->assetlog()->forceDelete();
            $license->licenseseats()->forceDelete();
            $license->forceDelete();
        }
    }

    private function purgeLocations(): void
    {
        $locations = Location::whereNotNull('deleted_at')->with('uploads')->withTrashed()->get();

        $this->info($locations->count() . ' locations purged.');
        foreach ($locations as $location) {
            $location->uploads->pluck('filename')->each(function ($filename) {
                try {
                    DeleteFile::run('private_uploads/locations' . '/' . $filename);
                } catch (Exception $e) {
                    Log::info('An error occurred while deleting files: ' . $e->getMessage());
                }
            });

            $this->info('- Location "' . $location->name . '" deleted.');
            $location->forceDelete();
            DeleteFile::run('locations/' . $location->image, 'public');
        }
    }

    private function purgeManufacturers(): void
    {
        $manufacturers = Manufacturer::whereNotNull('deleted_at')->withTrashed()->get();
        $this->info($manufacturers->count() . ' manufacturers purged.');
        foreach ($manufacturers as $manufacturer) {
            $this->info('- Manufacturer "' . $manufacturer->name . '" deleted.');
            $manufacturer->forceDelete();
            DeleteFile::run('manufacturers/' . $manufacturer->image, 'public');
        }
    }

    private function purgeStatusLabels(): void
    {
        $status_labels = Statuslabel::whereNotNull('deleted_at')->withTrashed()->get();
        $this->info($status_labels->count() . ' status labels purged.');
        foreach ($status_labels as $status_label) {
            $this->info('- Status Label "' . $status_label->name . '" deleted.');
            $status_label->forceDelete();
        }
    }

    private function purgeSuppliers(): void
    {
        $suppliers = Supplier::whereNotNull('deleted_at')->withTrashed()->get();
        $this->info($suppliers->count() . ' suppliers purged.');
        foreach ($suppliers as $supplier) {
            $this->info('- Supplier "' . $supplier->name . '" deleted.');
            $supplier->forceDelete();
            DeleteFile::run('suppliers/' . $supplier->image, 'public');
        }
    }

    private function purgeUsers(): void
    {
        $users = User::whereNotNull('deleted_at')->where('show_in_list', '!=', '0')->withTrashed()->get();

        $this->newLine();
        $this->info($users->count() . ' to be users purged.');
        $user_assoc = 0;
        foreach ($users as $user) {

            $rel_path = 'private_uploads/users';
            $filenames = Actionlog::where('action_type', 'uploaded')
                ->where('item_id', $user->id)
                ->pluck('filename');
            foreach ($filenames as $filename) {
                try {
                    DeleteFile::run($rel_path . '/' . $filename);
                } catch (Exception $e) {
                    Log::info('An error occurred while deleting files: ' . $e->getMessage());
                }
            }
            $this->info('- User "' . $user->username . '" deleted.');
            $user_assoc += $user->userlog()->count();
            $user->userlog()->forceDelete();
            $user->forceDelete();
            DeleteFile::run('avatars/' . $user->avatar, 'public');
        }
        $this->info($users->count() . ' users purged.');
        $this->info($user_assoc . ' corresponding user log records purged.');
    }
}
