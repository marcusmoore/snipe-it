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
        //     return $log->item()->is($testingAcceptance->checkoutable) && $log->created_at->is($testingAcceptance->created_at);
        // });
        //
        // dd($foundLog);

        // @todo: now loop through all $acceptances and match them to a $log

        // $acceptances->map(function (CheckoutAcceptance $acceptance) {
        //
        // });
    }
}
