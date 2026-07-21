<?php

namespace Tests\Feature\Helpers;

use App\Helpers\Helper;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use Tests\TestCase;

class CheckLowInventoryTest extends TestCase
{
    /**
     * Regression pin for the customer report where a consumable with
     * min_amt=1 and remaining=1 was flagged as "below the minimum required
     * quantity" in the alert menu and the daily inventory-alerts email.
     * Sitting exactly at min_amt is not below min_amt and must not appear.
     */
    public function test_consumable_at_min_amt_is_not_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        $consumable = Consumable::factory()->create(['qty' => 1, 'min_amt' => 1]);

        $this->assertNotContains(
            $consumable->id,
            $this->idsForType(Helper::checkLowInventory(), 'consumables'),
        );
    }

    public function test_consumable_below_min_amt_is_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        $consumable = Consumable::factory()->create(['qty' => 0, 'min_amt' => 1]);

        $this->assertContains(
            $consumable->id,
            $this->idsForType(Helper::checkLowInventory(), 'consumables'),
        );
    }

    public function test_consumable_above_min_amt_is_not_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        $consumable = Consumable::factory()->create(['qty' => 5, 'min_amt' => 1]);

        $this->assertNotContains(
            $consumable->id,
            $this->idsForType(Helper::checkLowInventory(), 'consumables'),
        );
    }

    public function test_consumable_with_null_min_amt_is_never_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        $consumable = Consumable::factory()->create(['qty' => 0, 'min_amt' => null]);

        $this->assertNotContains(
            $consumable->id,
            $this->idsForType(Helper::checkLowInventory(), 'consumables'),
        );
    }

    /**
     * alert_threshold is an early-warning buffer: with threshold=3 and
     * min_amt=5, warnings should start when only 2 units above min remain
     * (avail=7) and stop once the avail crosses the buffer edge upward
     * (avail=8 is comfortably outside the warning zone).
     */
    public function test_consumable_at_top_of_threshold_buffer_is_flagged()
    {
        $this->settings->set(['alert_threshold' => 3]);

        $consumable = Consumable::factory()->create(['qty' => 7, 'min_amt' => 5]);

        $this->assertContains(
            $consumable->id,
            $this->idsForType(Helper::checkLowInventory(), 'consumables'),
        );
    }

    public function test_consumable_just_outside_threshold_buffer_is_not_flagged()
    {
        $this->settings->set(['alert_threshold' => 3]);

        $consumable = Consumable::factory()->create(['qty' => 8, 'min_amt' => 5]);

        $this->assertNotContains(
            $consumable->id,
            $this->idsForType(Helper::checkLowInventory(), 'consumables'),
        );
    }

    public function test_accessory_at_min_amt_is_not_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        $accessory = Accessory::factory()->create(['qty' => 1, 'min_amt' => 1]);

        $this->assertNotContains(
            $accessory->id,
            $this->idsForType(Helper::checkLowInventory(), 'accessories'),
        );
    }

    public function test_accessory_below_min_amt_is_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        $accessory = Accessory::factory()->create(['qty' => 0, 'min_amt' => 1]);

        $this->assertContains(
            $accessory->id,
            $this->idsForType(Helper::checkLowInventory(), 'accessories'),
        );
    }

    public function test_component_at_min_amt_is_not_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        $component = Component::factory()->create(['qty' => 1, 'min_amt' => 1]);

        $this->assertNotContains(
            $component->id,
            $this->idsForType(Helper::checkLowInventory(), 'components'),
        );
    }

    public function test_component_below_min_amt_is_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        // Component's validation rule is qty >= 1, so drive "below min" via
        // a higher min_amt rather than a zero qty.
        $component = Component::factory()->create(['qty' => 1, 'min_amt' => 2]);

        $this->assertContains(
            $component->id,
            $this->idsForType(Helper::checkLowInventory(), 'components'),
        );
    }

    /**
     * The AssetModel branch counts RTD (Ready to Deploy, unassigned) assets
     * via the availableAssets() scope, so this test creates exactly min_amt
     * such assets to prove that hitting the floor without falling through
     * doesn't fire the alert.
     */
    public function test_asset_model_at_min_amt_is_not_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        $model = AssetModel::factory()->create(['min_amt' => 1]);
        Asset::factory()->create(['model_id' => $model->id]);

        $this->assertNotContains(
            $model->id,
            $this->idsForType(Helper::checkLowInventory(), 'models'),
        );
    }

    public function test_asset_model_below_min_amt_is_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        // min_amt=1 with zero RTD assets = below the floor.
        $model = AssetModel::factory()->create(['min_amt' => 1]);

        $this->assertContains(
            $model->id,
            $this->idsForType(Helper::checkLowInventory(), 'models'),
        );
    }

    /**
     * License seat records are auto-created by the License::created observer
     * (see app/Models/License.php), so a freshly-created license with seats=N
     * has N unassigned seats available.
     */
    public function test_license_at_min_amt_is_not_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        $license = License::factory()->create(['seats' => 1, 'min_amt' => 1]);

        $this->assertNotContains(
            $license->id,
            $this->idsForType(Helper::checkLowInventory(), 'licenses'),
        );
    }

    public function test_license_below_min_amt_is_flagged_as_low()
    {
        $this->settings->set(['alert_threshold' => 0]);

        // seats=1 with min_amt=2 means only 1 seat available where 2 is the floor.
        $license = License::factory()->create(['seats' => 1, 'min_amt' => 2]);

        $this->assertContains(
            $license->id,
            $this->idsForType(Helper::checkLowInventory(), 'licenses'),
        );
    }

    private function idsForType(array $items, string $type): array
    {
        return collect($items)
            ->where('type', $type)
            ->pluck('id')
            ->all();
    }
}
