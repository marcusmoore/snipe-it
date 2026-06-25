<?php

namespace Database\Factories;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CheckoutAcceptanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'checkoutable_type' => Asset::class,
            'checkoutable_id' => Asset::factory(),
            'assigned_to_id' => User::factory(),
        ];
    }

    protected static bool $skipActionLog = false;

    public function withoutActionLog(): static
    {
        // turn off for this create() call
        static::$skipActionLog = true;

        // ensure it turns back on AFTER creating
        return $this->afterCreating(function () {
            static::$skipActionLog = false;
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CheckoutAcceptance $acceptance) {
            if (static::$skipActionLog) {
                return; // short-circuit
            }
            if ($acceptance->checkoutable instanceof Asset) {
                $this->createdAssociatedActionLogEntry($acceptance);
            }

            if ($acceptance->checkoutable instanceof Asset && $acceptance->assignedTo instanceof User) {
                $asset = $acceptance->checkoutable;

                // Diagnostic snapshot BEFORE we touch the asset.
                $fillResult = array_keys($asset->fill([
                    'assigned_to' => $acceptance->assigned_to_id,
                    'assigned_type' => get_class($acceptance->assignedTo),
                ])->getDirty());

                // Now bypass mass-assignment entirely and set the attributes directly.
                $asset->assigned_to = $acceptance->assigned_to_id;
                $asset->assigned_type = get_class($acceptance->assignedTo);
                $dirtyAfterDirect = array_keys($asset->getDirty());

                $updated = $asset->save();

                // Re-read straight from the DB to see whether the write actually landed.
                $fresh = Asset::query()->withTrashed()->find($asset->id);

                if (! $updated || (int) $fresh?->assigned_to !== (int) $acceptance->assigned_to_id) {
                    throw new RuntimeException('PROBE asset assignment did not persist: '.json_encode([
                        'expected_assigned_to' => $acceptance->assigned_to_id,
                        'assigned_to_in_fillable' => in_array('assigned_to', $asset->getFillable(), true),
                        'fillable_count' => count($asset->getFillable()),
                        'model_unguarded' => Model::isUnguarded(),
                        'asset_class' => get_class($asset),
                        'dirty_after_fill' => $fillResult,
                        'dirty_after_direct_set' => $dirtyAfterDirect,
                        'save_returned' => $updated,
                        'in_memory_assigned_to' => $asset->assigned_to,
                        'fresh_db_assigned_to' => $fresh?->assigned_to,
                        'fresh_db_assigned_type' => $fresh?->assigned_type,
                        'validation_errors' => $asset->getErrors()->toArray(),
                        'asset_id' => $asset->id,
                        'connection' => $asset->getConnectionName(),
                    ]));
                }
            }
        });
    }

    public function forAccessory()
    {
        return $this->state([
            'checkoutable_type' => Accessory::class,
            'checkoutable_id' => Accessory::factory(),
        ]);
    }

    public function pending()
    {
        return $this->state([
            'accepted_at' => null,
            'declined_at' => null,
        ]);
    }

    public function accepted()
    {
        return $this->state([
            'accepted_at' => now()->subDay(),
            'declined_at' => null,
        ]);
    }

    public function withoutAlerting()
    {
        return $this->state(function () {
            return [
                'alert_on_response_id' => null,
            ];
        });
    }

    public function withAlertingTo(User $user)
    {
        return $this->state(function () use ($user) {
            return [
                'alert_on_response_id' => $user->id,
            ];
        });
    }

    private function createdAssociatedActionLogEntry(CheckoutAcceptance $acceptance): void
    {
        $acceptance->checkoutable->assetlog()->create([
            'action_type' => 'checkout',
            'target_id' => $acceptance->assigned_to_id,
            'target_type' => User::class,
            'item_id' => $acceptance->checkoutable_id,
            'item_type' => $acceptance->checkoutable_type,
        ]);
    }
}
