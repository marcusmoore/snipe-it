<?php

namespace App\Console\Commands;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CheckoutAcceptanceCleanup extends Command
{
    protected $signature = 'snipeit:checkout-acceptance-cleanup';

    protected $description = 'Cleans up CheckoutAcceptances that were incorrectly created.';

    public function handle(): void
    {
        $this->info('Starting: ' . now());

        $startTime = microtime(true);

        // fetch all pending checkout acceptances.
        // scoping pending works here because locations and assets cannot accept checkouts.
        $acceptances = CheckoutAcceptance::pending()->get();

        $this->info("{$acceptances->count()} CheckoutAcceptances retrieved");

        // get action log where the action is "checkout" and the target is Asset or Location.
        $logs = Actionlog::query()
            ->where([
                'action_type' => 'checkout',
            ])
            ->whereIn('target_type', [Asset::class, Location::class])
            ->get();

        $this->info("{$logs->count()} ActionLogs found");

        $progress = $this->output->createProgressBar($acceptances->count());
        $progress->start();

        $mapped = $acceptances
            ->map(function (CheckoutAcceptance $acceptance) use ($progress, $logs) {
                $log = $this->findLog($acceptance, $logs);

                if ($log) {
                    // attach log to acceptance
                    $acceptance->setRelation('checkoutActionLog', $log);
                }

                $progress->advance();

                return $acceptance;
            });

        $progress->finish();

        [$acceptancesWithLogs, $acceptancesWithoutLogs] = $mapped->partition(function (CheckoutAcceptance $acceptance) {
            return $acceptance->checkoutActionLog;
        });

        $this->newLine();
        $this->info("{$acceptancesWithLogs->count()} acceptances with matching log");
        $this->error("{$acceptancesWithoutLogs->count()} acceptances WITHOUT matching log");
        $this->newLine();

        $this->info('CheckoutAcceptances without matching log:');
        $this->table(['id', 'checkoutable_type', 'checkoutable_id', 'assigned_to_id'],
            $acceptancesWithoutLogs->map(function (CheckoutAcceptance $acceptance) {
                return [
                    'id' => $acceptance->id,
                    'checkoutable_type' => $acceptance->checkoutable_type,
                    'checkoutable_id' => $acceptance->checkoutable_id,
                    'assigned_to_id' => $acceptance->assigned_to_id,
                ];
            }));

        $this->info('Total time: ' . number_format(microtime(true) - $startTime, 2) . ' seconds');
    }

    private function findLog(CheckoutAcceptance $acceptance, Collection $logs)
    {
        $logsForCheckoutable = $logs->where(function (Actionlog $log) use ($acceptance) {
            return $log->item_type === $acceptance->checkoutable_type
                && $log->item_id === $acceptance->checkoutable_id;
        });

        if ($logsForCheckoutable->isEmpty()) {
            return null;
        }

        // check if there is an exact timestamp match
        $exactTimestampMatch = $logsForCheckoutable->where(function (Actionlog $log) use ($acceptance) {
            return $log->created_at->timestamp === $acceptance->created_at->timestamp;
        });

        // exact match found. return it.
        if ($exactTimestampMatch->count() === 1) {
            return $exactTimestampMatch->first();
        }

        // if there is not an exact match, return a roughly matched log
        return $logsForCheckoutable->first(function (Actionlog $log) use ($acceptance) {
            // check if the log's created_at is within 2 seconds of the acceptance's created_at
            return abs($log->created_at->timestamp - $acceptance->created_at->timestamp) <= 2;
        });
    }
}
