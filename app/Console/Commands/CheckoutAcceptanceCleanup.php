<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckoutAcceptanceCleanup extends Command
{
    protected $signature = 'snipeit:checkout-acceptance-cleanup';

    protected $description = 'Cleans up CheckoutAcceptances that were incorrectly created.';

    public function handle(): void
    {
        // fetch all pending checkout acceptances.
        // scoping "pending" works here because locations and assets cannot accept checkouts.
        $acceptances = DB::table('checkout_acceptances')
            ->whereNull(['accepted_at', 'declined_at', 'deleted_at'])
            ->get();

        $this->info("{$acceptances->count()} checkout acceptances retrieved for processing");

        // get action logs where the action is "checkout" and the target is Asset or Location.
        $logs = DB::table('action_logs')
            ->where('action_type', 'checkout')
            ->whereIn('target_type', [Asset::class, Location::class])
            ->whereNull('deleted_at')
            ->get();

        $this->info("{$logs->count()} ActionLogs found");

        $progress = $this->output->createProgressBar($acceptances->count());
        $progress->start();

        $mapped = $acceptances
            ->map(function ($acceptance) use ($progress, $logs) {
                $log = $this->findLog($acceptance, $logs);

                if ($log) {
                    // attach log to acceptance
                    // $acceptance->setRelation('checkoutActionLog', $log);
                    $acceptance->checkoutActionLog = $log;
                }

                $progress->advance();

                return $acceptance;
            });

        $progress->finish();

        [$acceptancesWithLogs, $acceptancesWithoutLogs] = $mapped->partition(function ($acceptance) {
            return isset($acceptance->checkoutActionLog);
        });

        $this->newLine();
        $this->info("{$acceptancesWithLogs->count()} acceptances with matching log");
        $this->error("{$acceptancesWithoutLogs->count()} acceptances WITHOUT matching log");
        $this->newLine();

        $this->info('CheckoutAcceptances without matching log:');
        $this->table(['id', 'checkoutable_type', 'checkoutable_id', 'assigned_to_id'],
            $acceptancesWithoutLogs->map(function ($acceptance) {
                return [
                    'id' => $acceptance->id,
                    'checkoutable_type' => $acceptance->checkoutable_type,
                    'checkoutable_id' => $acceptance->checkoutable_id,
                    'assigned_to_id' => $acceptance->assigned_to_id,
                ];
            }));
    }

    private function findLog($acceptance, Collection $logs)
    {
        $logsForCheckoutable = $logs->where(function ($log) use ($acceptance) {
            return $log->item_type === $acceptance->checkoutable_type
                && $log->item_id === $acceptance->checkoutable_id;
        });

        if ($logsForCheckoutable->isEmpty()) {
            return null;
        }

        // check if there is an exact timestamp match
        $exactTimestampMatch = $logsForCheckoutable->where(function ($log) use ($acceptance) {
            return $log->created_at === $acceptance->created_at;
        });

        // exact match found. return it.
        if ($exactTimestampMatch->count() === 1) {
            return $exactTimestampMatch->first();
        }

        // if there is not an exact match, return a roughly matched log
        return $logsForCheckoutable->first(function ($log) use ($acceptance) {
            $logCreatedAt = Carbon::parse($log->created_at);
            $acceptanceCreatedAt = Carbon::parse($acceptance->created_at);

            // check if the log's created_at is within 2 seconds of the acceptance's created_at
            return abs($logCreatedAt->timestamp - $acceptanceCreatedAt->timestamp) <= 2;
        });
    }
}
