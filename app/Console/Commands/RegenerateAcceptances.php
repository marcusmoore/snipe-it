<?php

namespace App\Console\Commands;

use App\Actions\Acceptances\RegenerateAcceptancesAction;
use App\Actions\Acceptances\RegenerateAcceptancesResult;
use Illuminate\Console\Command;

class RegenerateAcceptances extends Command
{
    protected $signature = 'snipeit:regenerate-acceptances
        {--category=* : Limit to these category ids (default: all categories requiring acceptance)}
        {--company=* : Limit to these company ids (default: all companies)}
        {--dry-run : Print the report; create nothing}
        {--exclude-declined : Skip users whose latest response was a decline (default: re-ask them)}
        {--notify : Also email each affected user}';

    protected $description = 'Re-request EULA acceptance from users who currently hold items';

    public function handle(): int
    {
        $result = RegenerateAcceptancesAction::run(
            categoryIds: $this->option('category'),
            companyIds: $this->option('company'),
            excludeDeclined: (bool) $this->option('exclude-declined'),
            dryRun: (bool) $this->option('dry-run'),
            notify: (bool) $this->option('notify'),
        );

        return $this->printReport($result);
    }

    /**
     * Prints what the run re-requested, or under --dry-run what it would have.
     */
    private function printReport(RegenerateAcceptancesResult $result): int
    {
        if ($result->candidateCount === 0) {
            $this->info('No users currently hold items requiring acceptance in that scope.');

            return 0;
        }

        if ($result->reportRows !== []) {
            $this->table(['User', 'Item', 'Type', 'Units held', 'Qty'], $result->reportRows);
        }

        $this->info('To re-request: '.count($result->reportRows).'.');

        foreach ($result->sendCountsByType as $type => $count) {
            $this->line('  '.$type.': '.$count);
        }

        $this->info('Previously declined: '.$result->previouslyDeclined.'.');
        $this->info('Already covered by a pending request: '.$result->alreadyCovered.'.');

        if ($result->excludeDeclined) {
            $this->info('Previously declined and excluded: '.$result->declinedAndExcluded.'.');
        }

        $this->info($result->dryRun ? 'Nothing was created.' : 'Created: '.$result->created.'.');

        if ($result->notify) {
            $this->info('Notified: '.$result->notified.'.');

            if ($result->holdersWithoutEmail !== []) {
                $this->info('The following users do not have an email address:');
                $this->table(['ID', 'Name'], $result->holdersWithoutEmail);
            }
        }

        return 0;
    }
}
