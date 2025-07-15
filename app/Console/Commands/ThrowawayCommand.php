<?php

namespace App\Console\Commands;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Location;
use Illuminate\Console\Command;

class ThrowawayCommand extends Command
{
    protected $signature = 'throwaway';

    protected $description = 'Command description';

    public function handle(): void
    {
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
                $this->newLine();
                $this->line("Processing CheckoutAcceptance:{$acceptance->id}");

                $log = $logs->first(function (Actionlog $log) use ($acceptance) {
                    return $log->item_type === $acceptance->checkoutable_type
                        && $log->item_id === $acceptance->checkoutable_id
                        && $log->created_at->timestamp === $acceptance->created_at->timestamp;
                });

                if ($log) {
                    // attach log to acceptance
                    $acceptance->setRelation('checkoutActionLog', $log);
                } else {
                    $this->line("No matching log found for CheckoutAcceptance:{$acceptance->id}");
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
    }
}
