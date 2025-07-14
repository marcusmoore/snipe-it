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

        // /** @var CheckoutAcceptance $testingAcceptance */
        // $testingAcceptance = $acceptances->firstWhere('id', 439);
        //
        // $foundLog = $logs->filter(function ($log) use ($testingAcceptance) {
        //     return $log->item_type === $testingAcceptance->checkoutable_type
        //         && $log->item_id === $testingAcceptance->checkoutable_id
        //         && $log->created_at->timestamp === $testingAcceptance->created_at->timestamp;
        // });
        //
        // dd($foundLog);

        // @todo: now loop through all $acceptances and match them to a $log
        $acceptances->map(function (CheckoutAcceptance $acceptance) use ($logs) {
            $this->line("Processing CheckoutAcceptance:{$acceptance->id}");

            $log = $logs->first(function (Actionlog $log) use ($acceptance) {
                return $log->item_type === $acceptance->checkoutable_type
                    && $log->item_id === $acceptance->checkoutable_id
                    && $log->created_at->timestamp === $acceptance->created_at->timestamp;
            });

            if ($log) {

            } else {
                $this->error("No matching log found for CheckoutAcceptance:{$acceptance->id}");
            }
        });
    }
}
