<?php

namespace App\Actions\Manufacturers;

use App\Exceptions\ItemStillHasAccessories;
use App\Exceptions\ItemStillHasAssets;
use App\Exceptions\ItemStillHasComponents;
use App\Exceptions\ItemStillHasConsumables;
use App\Exceptions\ItemStillHasLicenses;
use App\Models\Manufacturer;

class DeleteManufacturerAction
{
    /**
     * @throws ItemStillHasAssets
     * @throws ItemStillHasComponents
     * @throws ItemStillHasAccessories
     * @throws ItemStillHasLicenses
     * @throws ItemStillHasConsumables
     */
    static function run(Manufacturer $manufacturer): bool
    {
        $manufacturer->loadCount([
            'assets as assets_count',
            'accessories as accessories_count',
            'consumables as consumables_count',
            'components as components_count',
            'licenses as licenses_count',
        ]);

        if ($manufacturer->assets_count > 0) {
            throw new ItemStillHasAssets($manufacturer);
        }
        if ($manufacturer->accessories_count > 0) {
            throw new ItemStillHasAccessories($manufacturer);
        }
        if ($manufacturer->consumables_count > 0) {
            throw new ItemStillHasConsumables($manufacturer);
        }
        if ($manufacturer->components_count > 0) {
            throw new ItemStillHasComponents($manufacturer);
        }
        if ($manufacturer->licenses_count > 0) {
            throw new ItemStillHasLicenses($manufacturer);
        }

        $manufacturer->delete();

        return true;
    }

}
