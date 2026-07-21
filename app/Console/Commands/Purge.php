<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\License;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;

class Purge extends Command
{
    /**
     * The `--force=true` back-compat: the settings UI at
     * SettingsController::postPurge() calls this with
     * `['--force' => 'true', '--no-interaction' => true]`.
     *
     * @var string
     */
    protected $signature = 'snipeit:purge
        {--force=false : Skip the confirmation prompt (accepts "true").}
        {--dry-run : Report what would be purged without deleting anything.}';

    protected $description = 'Purge all soft-deleted records in the database. Walks every model that uses the SoftDeletes trait, DELETEs the trashed rows, and cleans up their polymorphic action_log children. No undo.';

    /**
     * Users are excluded even when soft-deleted if show_in_list='0'. System
     * users (LDAP-sync placeholders, etc.) set this to '0' and shouldn't be
     * garbage-collected here.
     */
    private const PARENT_QUERY_FILTERS = [
        User::class => [['column' => 'show_in_list', 'op' => '!=', 'value' => '0']],
    ];

    /**
     * Non-soft-deletable FK children that should be nuked when their parent
     * is purged, even if the child itself was not soft-deleted. Auto-
     * discovery finds all soft-deletable models, but a trashed License with
     * live LicenseSeats or a trashed Asset with live Maintenances would
     * leave orphans behind if we only nuked soft-deleted rows. This map
     * covers the parent→child relations where the parent "owns" the child
     * outright and orphaning it makes no sense.
     */
    private const FK_CHILDREN = [
        Asset::class => [
            ['table' => 'maintenances', 'foreign_key' => 'asset_id'],
        ],
        License::class => [
            ['table' => 'license_seats', 'foreign_key' => 'license_id'],
        ],
    ];

    public function handle(): int
    {
        $force = $this->option('force') === 'true';
        $dryRun = (bool) $this->option('dry-run');

        if (! $force) {
            warning('This will PERMANENTLY delete every soft-deleted record in the database. There is no undo.');

            if (! confirm('Continue with the purge?', default: false)) {
                $this->info('Cancelled. Nothing was purged.');

                return self::SUCCESS;
            }
        }

        $started = microtime(true);
        $summary = [];

        foreach ($this->discoverSoftDeletableModels() as $modelClass) {
            foreach ($this->purgeModel($modelClass, $dryRun) as $row) {
                $summary[] = $row;
            }
        }

        $elapsed = microtime(true) - $started;

        $this->newLine();

        if ($dryRun) {
            $this->components->info('Dry run complete. No records were deleted.');
        } else {
            $this->components->info('Purge complete.');
        }

        if (empty($summary)) {
            $this->info('Nothing to purge.');
        } else {
            $this->table(['Resource', $dryRun ? 'Would purge' : 'Purged'], $summary);
        }

        $this->info(sprintf('Elapsed: %.3fs   Peak memory: %s MB',
            $elapsed,
            round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ));

        return self::SUCCESS;
    }

    /**
     * Walk app/Models/ and return the FQCNs of every concrete Eloquent
     * model that uses the SoftDeletes trait. Runtime discovery means a new
     * soft-deletable model gets picked up automatically without touching
     * this command.
     *
     * Dedupe by table so single-table inheritance / subclassed models
     * (e.g. SCIMUser extends User, same `users` table) don't get processed
     * twice — the first pass would run without the subclass-specific
     * filters (`show_in_list != 0` for users) and delete the rows the
     * parent's filter was supposed to preserve. Prefer the base class:
     * the more-derived class is skipped if a parent for its table was
     * already added.
     *
     * @return list<class-string<Model>>
     */
    private function discoverSoftDeletableModels(): array
    {
        $candidates = [];
        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }
            $ref = new ReflectionClass($class);
            if ($ref->isAbstract() || ! $ref->isSubclassOf(Model::class)) {
                continue;
            }
            if (! in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                continue;
            }
            $candidates[] = $class;
        }

        // Dedupe by table, keeping the base-most class in each group so
        // filters on the parent class apply. Sort by inheritance depth
        // ascending, then walk and keep the first per table.
        usort($candidates, fn ($a, $b) => count(class_parents($a)) <=> count(class_parents($b)));

        $seen = [];
        $result = [];
        foreach ($candidates as $class) {
            $table = (new $class)->getTable();
            if (isset($seen[$table])) {
                continue;
            }
            $seen[$table] = true;
            $result[] = $class;
        }

        return $result;
    }

    /**
     * Nuke one model's trashed rows: pluck the trashed ids, wipe their
     * polymorphic action_log children (via `item_type`/`item_id` and
     * `target_type`/`target_id`), then bulk-delete the parents by their
     * `deleted_at` index.
     *
     * Children go per-row DELETE against the composite index on
     * action_logs — bulk WHERE IN and JOIN DELETE both benchmarked ~3x
     * slower on MariaDB (InnoDB commits small autocommit transactions
     * faster than one long one against the composite index).
     *
     * @return array<int, array{0: string, 1: int}>
     */
    private function purgeModel(string $modelClass, bool $dryRun): array
    {
        $model = new $modelClass;
        $table = $model->getTable();
        $label = class_basename($modelClass);

        $parentQuery = DB::table($table)->whereNotNull('deleted_at');
        foreach (self::PARENT_QUERY_FILTERS[$modelClass] ?? [] as $f) {
            $parentQuery->where($f['column'], $f['op'], $f['value']);
        }

        $ids = (clone $parentQuery)->pluck('id');
        if ($ids->isEmpty()) {
            return [];
        }

        $rows = [];

        // Polymorphic action_log cleanup. Every model referenced by
        // action_logs uses one of two column pairs. Users use target_*,
        // everything else uses item_*.
        $itemLogs = 0;
        $targetLogs = 0;
        foreach ($ids as $id) {
            if ($dryRun) {
                $itemLogs += DB::table('action_logs')
                    ->where('item_type', $modelClass)
                    ->where('item_id', $id)
                    ->count();
                $targetLogs += DB::table('action_logs')
                    ->where('target_type', $modelClass)
                    ->where('target_id', $id)
                    ->count();
            } else {
                $itemLogs += DB::table('action_logs')
                    ->where('item_type', $modelClass)
                    ->where('item_id', $id)
                    ->delete();
                $targetLogs += DB::table('action_logs')
                    ->where('target_type', $modelClass)
                    ->where('target_id', $id)
                    ->delete();
            }
        }

        // FK-child cleanup: rows in other tables that belong to a trashed
        // parent by a plain foreign key (see FK_CHILDREN docblock). These
        // are nuked whole rather than only-trashed because a live
        // LicenseSeat pointing at a purged License is an orphan by
        // definition.
        $fkChildCounts = [];
        foreach (self::FK_CHILDREN[$modelClass] ?? [] as $child) {
            $count = 0;
            foreach ($ids as $id) {
                $q = DB::table($child['table'])->where($child['foreign_key'], $id);
                $count += $dryRun ? $q->count() : $q->delete();
            }
            if ($count > 0) {
                $fkChildCounts[$child['table']] = $count;
            }
        }

        $parentCount = $dryRun ? $ids->count() : $parentQuery->delete();

        $rows[] = [$label, $parentCount];
        if ($itemLogs > 0) {
            $rows[] = [$label.' action_logs (item)', $itemLogs];
        }
        if ($targetLogs > 0) {
            $rows[] = [$label.' action_logs (target)', $targetLogs];
        }
        foreach ($fkChildCounts as $table => $count) {
            $rows[] = [$table, $count];
        }

        return $rows;
    }
}
