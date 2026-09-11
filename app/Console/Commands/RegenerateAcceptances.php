<?php

namespace App\Console\Commands;

use App\Actions\Acceptances\CreateCheckoutAcceptanceAction;
use App\Mail\AcceptanceReRequestMail;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CheckoutAcceptance;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\LicenseSeat;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * Re-requests EULA acceptance from users who currently hold items.
 *
 * @phpstan-type Checkoutable Accessory|Asset|Component|Consumable|LicenseSeat
 * @phpstan-type Candidate array{item: Checkoutable, user: User, units: int}
 * @phpstan-type ClassifiedCandidate array{item: Checkoutable, user: User, units: int, qty: int, outcome: string, declined: bool}
 */
class RegenerateAcceptances extends Command
{
    protected $signature = 'snipeit:regenerate-acceptances
        {--category=* : Limit to these category ids (default: all categories requiring acceptance)}
        {--company=* : Limit to these company ids (default: all companies)}
        {--dry-run : Print the report; create nothing}
        {--exclude-declined : Skip users whose latest response was a decline (default: re-ask them)}
        {--notify : Also email each affected user}';

    protected $description = 'Re-request EULA acceptance from users who currently hold items';

    /**
     * How many items to load per builder query. Each chunk of items costs one
     * acceptance-history query, however many holders those items turn out to have.
     */
    private const CHUNK_SIZE = 500;

    private const OUTCOME_SEND = 'send';

    private const OUTCOME_COVERED = 'covered';

    private const OUTCOME_DECLINED_EXCLUDED = 'declined_excluded';

    /**
     * Category ids passed via --category, empty when the operator did not filter.
     *
     * @var array<int, string>
     */
    private array $categoryIds = [];

    /**
     * Company ids passed via --company, empty when the operator did not filter.
     *
     * @var array<int, string>
     */
    private array $companyIds = [];

    private bool $excludeDeclined = false;

    private bool $dryRun = false;

    private bool $notify = false;

    /**
     * The holders this run created rows for, keyed by user id, each with the items they
     * were re-requested for. Keying by holder is what keeps a holder re-requested for
     * three items to one email rather than three.
     *
     * The items are scalars, never the models: holding a checkoutable per created row
     * would pin every item this run touches in memory and undo the chunking that bounds
     * the command.
     *
     * @var array<int, array{user: User, items: array<int, array{name: string, type: class-string, qty: int|null}>}>
     */
    private array $holdersToNotify = [];

    /**
     * Holders who got rows but could not be emailed, as ID/name table rows.
     *
     * @var array<int, array{0: int, 1: string}>
     */
    private array $holdersWithoutEmail = [];

    /**
     * The rows this run re-requested, in the order the builders found them. Under
     * --dry-run they are the rows it would have created.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: int, 4: int}>
     */
    private array $reportRows = [];

    /**
     * How many pairs each checkoutable type contributed to the report.
     *
     * @var array<string, int>
     */
    private array $sendCountsByType = [];

    private int $candidateCount = 0;

    private int $previouslyDeclined = 0;

    private int $alreadyCovered = 0;

    private int $declinedAndExcluded = 0;

    private int $created = 0;

    private int $notified = 0;

    public function handle(): int
    {
        $this->categoryIds = $this->option('category');
        $this->companyIds = $this->option('company');
        $this->excludeDeclined = (bool) $this->option('exclude-declined');
        $this->dryRun = (bool) $this->option('dry-run');
        $this->notify = (bool) $this->option('notify');

        $this->findAssetCandidates();
        $this->findLicenseSeatCandidates();
        $this->findAccessoryCandidates();
        $this->findConsumableCandidates();
        $this->findComponentCandidates();

        $this->notifyHolders();

        return $this->printReport();
    }

    /**
     * Assets assigned directly to a user, plus assets assigned to another asset
     * that is itself assigned to a user.
     *
     * The category is reached through `model.category` rather than the tidier
     * `Asset::category()` relation because `Asset::category()` is a `hasOneThrough`,
     * so Laravel adds a `SoftDeletableHasManyThrough` global scope that excludes
     * assets whose AssetModel is soft-deleted.
     * A live checkout of such an asset asks `requireAcceptance()`
     * instead, which reads `$this->model->category` where `model()` is
     * `belongsTo(...)->withTrashed()` — so it says yes and creates an acceptance row.
     * Going through the relation would make this command regenerate a smaller set than
     * a live checkout creates, which is the divergence it exists to remove.
     */
    private function findAssetCandidates(): void
    {
        Asset::query()
            ->whereHas('model.category', fn (Builder $q) => $this->scopeToRequestedCategories($q))
            ->when($this->companyIds, fn (Builder $q) => $q->whereIn('assets.company_id', $this->companyIds))
            ->whereIn('assets.assigned_type', [User::class, Asset::class])
            ->with('assignedTo')
            ->chunkById(self::CHUNK_SIZE, function (EloquentCollection $assets): void {
                $candidates = [];

                foreach ($assets as $asset) {
                    if ($user = $this->resolveHolder($this->assignedTarget($asset))) {
                        $candidates[] = ['item' => $asset, 'user' => $user, 'units' => 1];
                    }
                }

                $this->classifyChunk($candidates);
            });
    }

    /**
     * License seats, keyed on the asset they are attached to where there is one.
     *
     * `license_seats.assigned_to` is denormalised and unreliable in both directions:
     * the API asset-checkout path attaches a seat without ever stamping it, and five
     * checkin paths clear it while deliberately leaving `asset_id` set. So a seat with
     * an `asset_id` takes its holder from that asset's *current* assignment, and only a
     * seat with no asset falls back to `assigned_to`. Either way a seat yields at most
     * one pair, so seats carrying both columns cannot double-count.
     */
    private function findLicenseSeatCandidates(): void
    {
        LicenseSeat::query()
            ->whereHas('license.category', fn (Builder $q) => $this->scopeToRequestedCategories($q))
            ->when($this->companyIds, fn (Builder $q) => $q->whereHas('license',
                fn (Builder $license) => $license->whereIn('licenses.company_id', $this->companyIds)
            ))
            ->byAssigned()
            ->with(['asset.assignedTo', 'user'])
            ->chunkById(self::CHUNK_SIZE, function (EloquentCollection $seats): void {
                $candidates = [];

                foreach ($seats as $seat) {
                    $user = $seat->asset_id
                        ? $this->resolveHolder($seat->asset)
                        : $seat->user;

                    if ($user instanceof User) {
                        $candidates[] = ['item' => $seat, 'user' => $user, 'units' => 1];
                    }
                }

                $this->classifyChunk($candidates);
            });
    }

    /**
     * Accessories, one pivot row per unit held. Rows assigned to a location are never
     * candidates; rows assigned to an asset resolve to that asset's holder.
     */
    private function findAccessoryCandidates(): void
    {
        Accessory::query()
            ->whereHas('category', fn (Builder $q) => $this->scopeToRequestedCategories($q))
            ->when($this->companyIds, fn (Builder $q) => $q->whereIn('accessories.company_id', $this->companyIds))
            ->whereHas('checkouts', fn (Builder $q) => $q->whereIn('assigned_type', [User::class, Asset::class]))
            ->with(['checkouts' => fn ($q) => $q->whereIn('assigned_type', [User::class, Asset::class])])
            ->chunkById(self::CHUNK_SIZE, function (EloquentCollection $accessories): void {
                $candidates = [];

                foreach ($accessories as $accessory) {
                    $unitsPerUser = [];
                    $usersById = [];

                    foreach ($accessory->checkouts as $checkout) {
                        if (! $user = $this->resolveHolder($this->assignedTarget($checkout))) {
                            continue;
                        }

                        $usersById[$user->id] = $user;
                        $unitsPerUser[$user->id] = ($unitsPerUser[$user->id] ?? 0) + 1;
                    }

                    foreach ($unitsPerUser as $userId => $units) {
                        $candidates[] = ['item' => $accessory, 'user' => $usersById[$userId], 'units' => $units];
                    }
                }

                $this->classifyChunk($candidates);
            });
    }

    /**
     * Consumables, one pivot row per unit held. `consumables_users` has no
     * `assigned_type` column, so consumables are user-only by schema.
     */
    private function findConsumableCandidates(): void
    {
        Consumable::query()
            ->whereHas('category', fn (Builder $q) => $this->scopeToRequestedCategories($q))
            ->when($this->companyIds, fn (Builder $q) => $q->whereIn('consumables.company_id', $this->companyIds))
            ->has('users')
            ->with('users')
            ->chunkById(self::CHUNK_SIZE, function (EloquentCollection $consumables): void {
                $candidates = [];

                foreach ($consumables as $consumable) {
                    $unitsPerUser = [];
                    $usersById = [];

                    foreach ($consumable->users as $user) {
                        $usersById[$user->id] = $user;
                        $unitsPerUser[$user->id] = ($unitsPerUser[$user->id] ?? 0) + 1;
                    }

                    foreach ($unitsPerUser as $userId => $units) {
                        $candidates[] = ['item' => $consumable, 'user' => $usersById[$userId], 'units' => $units];
                    }
                }

                $this->classifyChunk($candidates);
            });
    }

    /**
     * Components, whose holders are always reached through an asset. Unlike the other
     * pivots, `components_assets` carries an `assigned_qty` column rather than writing
     * one row per unit, so units are summed from that column.
     */
    private function findComponentCandidates(): void
    {
        Component::query()
            ->whereHas('category', fn (Builder $q) => $this->scopeToRequestedCategories($q))
            ->when($this->companyIds, fn (Builder $q) => $q->whereIn('components.company_id', $this->companyIds))
            ->has('assets')
            ->with('assets.assignedTo')
            ->chunkById(self::CHUNK_SIZE, function (EloquentCollection $components): void {
                $candidates = [];

                foreach ($components as $component) {
                    $unitsPerUser = [];
                    $usersById = [];

                    foreach ($component->assets as $asset) {
                        if (! $user = $this->resolveHolder($asset)) {
                            continue;
                        }

                        $usersById[$user->id] = $user;
                        $unitsPerUser[$user->id] = ($unitsPerUser[$user->id] ?? 0) + (int) $asset->pivot->assigned_qty;
                    }

                    foreach ($unitsPerUser as $userId => $units) {
                        $candidates[] = ['item' => $component, 'user' => $usersById[$userId], 'units' => $units];
                    }
                }

                $this->classifyChunk($candidates);
            });
    }

    /**
     * Classifies one builder's chunk of candidates, tallies each outcome, and creates
     * the rows for the ones to re-request unless this is a --dry-run.
     *
     * Creating inside the chunk keeps the run's memory bounded by the chunk rather than
     * by the size of the send set. It is safe against the chunking itself: a new
     * acceptance row cannot change which items a later chunk of items returns, and a
     * pair is only ever classified once, by exactly one builder.
     *
     * @param  array<int, Candidate>  $candidates
     */
    private function classifyChunk(array $candidates): void
    {
        if ($candidates === []) {
            return;
        }

        $history = $this->acceptanceHistory($candidates);

        foreach ($candidates as $candidate) {
            $key = $this->pairKey($candidate['item']->getKey(), $candidate['user']->getKey());

            $pair = $this->classify($candidate, $history[$key] ?? new EloquentCollection);

            $this->recordOutcome($pair);

            if ($pair['outcome'] === self::OUTCOME_SEND && ! $this->dryRun) {
                $this->createAcceptance($pair);
            }
        }
    }

    /**
     * Decides what to do with one pair by comparing the units it holds against the
     * pending acceptance rows that already cover them.
     *
     * Accepted rows deliberately do not count as coverage — re-asking a holder who
     * accepted is the whole point of the command. Only an in-flight *pending* ask
     * counts, and it counts by quantity rather than existence: a user holding three
     * accessory units with one pending unit is still under-covered for two, so the
     * shortfall is what gets re-requested and the existing pending row is left alone.
     * Where a holder can only ever hold one of a thing (an asset, a license seat) the
     * quantity comparison collapses into "is there a pending row".
     *
     * @param  Candidate  $candidate
     * @param  EloquentCollection<int, CheckoutAcceptance>  $history
     * @return ClassifiedCandidate
     */
    private function classify(array $candidate, EloquentCollection $history): array
    {
        $pendingCoverage = $history
            ->filter(fn (CheckoutAcceptance $acceptance) => $acceptance->isPending())
            ->sum(fn (CheckoutAcceptance $acceptance) => $acceptance->qty ?? 1);

        $shortfall = $candidate['units'] - $pendingCoverage;
        $declined = $this->latestResponse($history)?->declined_at !== null;

        return [
            ...$candidate,
            'qty' => $shortfall,
            'declined' => $declined,
            'outcome' => match (true) {
                $shortfall <= 0 => self::OUTCOME_COVERED,
                $declined && $this->excludeDeclined => self::OUTCOME_DECLINED_EXCLUDED,
                default => self::OUTCOME_SEND,
            },
        ];
    }

    /**
     * Tallies one classified pair, adding a report row when it is one to re-request.
     *
     * @param  ClassifiedCandidate  $pair
     */
    private function recordOutcome(array $pair): void
    {
        $this->candidateCount++;

        if ($pair['outcome'] === self::OUTCOME_COVERED) {
            $this->alreadyCovered++;

            return;
        }

        if ($pair['declined']) {
            $this->previouslyDeclined++;
        }

        if ($pair['outcome'] === self::OUTCOME_DECLINED_EXCLUDED) {
            $this->declinedAndExcluded++;

            return;
        }

        $type = class_basename($pair['item']);
        $this->sendCountsByType[$type] = ($this->sendCountsByType[$type] ?? 0) + 1;

        $this->reportRows[] = [
            $pair['user']->present()->fullName,
            $pair['item']->present()->name,
            $type,
            $pair['units'],
            $pair['qty'],
        ];
    }

    /**
     * Creates the pending row that re-requests acceptance from one holder.
     *
     * The row deliberately carries no `alert_on_response_id`, so nobody is emailed when
     * the holder answers. That id names whoever *performed a checkout*, which is why the
     * listener reads it from `auth()->id()` — and a console run has no actor to read.
     * Carrying the last checkout's admin forward would invent an answer: the column holds
     * a single id, so aggregating a pair's several prior rows into one re-request has to
     * drop every admin but one, silently. Leaving it null keeps that decision unmade
     * rather than making it wrong, and adding a recipient later is additive.
     *
     * @param  ClassifiedCandidate  $pair
     */
    private function createAcceptance(array $pair): void
    {
        CreateCheckoutAcceptanceAction::run(
            $pair['item'],
            $pair['user'],
            $this->creationQty($pair['item'], $pair['qty']),
        );

        $this->created++;

        $holder = $pair['user'];
        $this->holdersToNotify[$holder->id]['user'] = $holder;
        $this->holdersToNotify[$holder->id]['items'][] = [
            'name' => $pair['item']->present()->name,
            'type' => $pair['item']::class,
            'qty' => $this->creationQty($pair['item'], $pair['qty']),
        ];
    }

    /**
     * Emails the holders this run created rows for, when --notify was passed.
     *
     * One message per holder, never one per row: a holder re-requested for three items
     * is asked once, for three items. A holder with no email address still keeps their
     * rows — they will see them on /account/accept at their next login, just without the
     * nudge — and is reported instead.
     */
    private function notifyHolders(): void
    {
        if (! $this->notify) {
            return;
        }

        foreach ($this->holdersToNotify as $holder) {
            $user = $holder['user'];

            if (! $user->email) {
                $this->holdersWithoutEmail[] = [$user->id, $user->present()->fullName];

                continue;
            }

            $mail = new AcceptanceReRequestMail($user, $holder['items']);

            Mail::to($user->email)->send($user->locale ? $mail->locale($user->locale) : $mail);

            $this->notified++;
        }
    }

    /**
     * The `qty` to stamp on the new row: the shortfall for the types a holder can hold
     * several of, and null for an asset or a license seat.
     *
     * Null is what today's asset and license-seat checkout paths write — an asset or a
     * seat is a single thing — and null already means one unit everywhere it is read.
     * Writing the shortfall there instead would make this command's rows differ from a
     * live checkout's for no gain.
     *
     * @param  Checkoutable  $item
     */
    private function creationQty(Model $item, int $shortfall): ?int
    {
        return $item instanceof Asset || $item instanceof LicenseSeat
            ? null
            : $shortfall;
    }

    /**
     * The pair's most recent answered acceptance, or null when they never answered one.
     *
     * "Most recent" is the highest id — acceptance timestamps have second granularity
     * and nothing backdates a row. Pending rows are skipped rather than treated as the
     * latest word, because a re-request's own pending row would otherwise bury the
     * decline it superseded and make a decliner look like someone who never answered.
     *
     * @param  EloquentCollection<int, CheckoutAcceptance>  $history
     */
    private function latestResponse(EloquentCollection $history): ?CheckoutAcceptance
    {
        return $history
            ->reject(fn (CheckoutAcceptance $acceptance) => $acceptance->isPending())
            ->sortByDesc('id')
            ->first();
    }

    /**
     * Every acceptance row belonging to a pair in this chunk, keyed by (item, user).
     *
     * A chunk always comes from one builder, so one `checkoutable_type` covers it. The
     * query over-fetches — cross pairs (item A × user 2 when only A × 1 and B × 2 are
     * wanted) and each pair's full history — which is harmless and much cheaper than a
     * query per pair. Soft-deleted rows stay invisible: a trashed accepted row making
     * its pair look never-asked is what compliance wants.
     *
     * @param  array<int, Candidate>  $candidates
     * @return array<string, EloquentCollection<int, CheckoutAcceptance>>
     */
    private function acceptanceHistory(array $candidates): array
    {
        $itemIds = [];
        $userIds = [];

        foreach ($candidates as $candidate) {
            $itemIds[] = $candidate['item']->getKey();
            $userIds[] = $candidate['user']->getKey();
        }

        return CheckoutAcceptance::query()
            ->where('checkoutable_type', $candidates[0]['item']->getMorphClass())
            ->whereIn('checkoutable_id', array_unique($itemIds))
            ->whereIn('assigned_to_id', array_unique($userIds))
            ->get()
            ->groupBy(fn (CheckoutAcceptance $acceptance) => $this->pairKey(
                $acceptance->checkoutable_id,
                $acceptance->assigned_to_id,
            ))
            ->all();
    }

    /**
     * The (checkoutable, user) pair as one lookup key, scoped to a single chunk.
     */
    private function pairKey(int|string $itemId, int|string $userId): string
    {
        return $itemId.'|'.$userId;
    }

    /**
     * Prints what this run re-requested, or under --dry-run what it would have.
     */
    private function printReport(): int
    {
        if ($this->candidateCount === 0) {
            $this->info('No users currently hold items requiring acceptance in that scope.');

            return 0;
        }

        if ($this->reportRows !== []) {
            $this->table(['User', 'Item', 'Type', 'Units held', 'Qty'], $this->reportRows);
        }

        $this->info('To re-request: '.count($this->reportRows).'.');

        foreach ($this->sendCountsByType as $type => $count) {
            $this->line('  '.$type.': '.$count);
        }

        $this->info('Previously declined: '.$this->previouslyDeclined.'.');
        $this->info('Already covered by a pending request: '.$this->alreadyCovered.'.');

        if ($this->excludeDeclined) {
            $this->info('Previously declined and excluded: '.$this->declinedAndExcluded.'.');
        }

        $this->info($this->dryRun ? 'Nothing was created.' : 'Created: '.$this->created.'.');

        if ($this->notify) {
            $this->info('Notified: '.$this->notified.'.');

            if ($this->holdersWithoutEmail !== []) {
                $this->info('The following users do not have an email address:');
                $this->table(['ID', 'Name'], $this->holdersWithoutEmail);
            }
        }

        return 0;
    }

    /**
     * Reads a model's assignment target, eager-loaded or not.
     *
     * `Asset::assignedTo()` and `AccessoryCheckout::assignedTo()` are declared
     * `morphTo('assigned', ...)`, so the morph name and the method name differ. Eager
     * loading stores the result under the *morph* name — `with('assignedTo')` leaves
     * `$model->assignedTo` null and puts the real model on `$model->assigned`, which is
     * why AssetsTransformer::transformAssignedTo() reads `$asset->assigned`. Reading the
     * loaded relation when it is there and falling back to the method otherwise keeps
     * this correct whether or not the caller eager-loaded it.
     */
    private function assignedTarget(Asset|AccessoryCheckout $model): ?Model
    {
        return $model->relationLoaded('assigned')
            ? $model->getRelation('assigned')
            : $model->assignedTo;
    }

    /**
     * Walks a checkout target down to the user who holds it, mirroring
     * CheckoutableListener::resolveAcceptanceTarget(). A user holds their own items;
     * an asset's items are held by whoever that asset is assigned to. Anything else
     * — a location, an unassigned asset — has no holder. Soft-deleted users are not
     * holders either: every relation this walks is declared withTrashed(), so a deleted
     * user would otherwise come back and be re-asked to accept.
     */
    private function resolveHolder(?Model $target): ?User
    {
        if ($target instanceof User) {
            return $target->trashed() ? null : $target;
        }

        if ($target instanceof Asset) {
            $holder = $this->assignedTarget($target);

            return $holder instanceof User ? $this->resolveHolder($holder) : null;
        }

        return null;
    }

    /**
     * Narrows a Category query to the categories this run cares about.
     *
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    private function scopeToRequestedCategories(Builder $query): Builder
    {
        return $query->requiresAcceptance()
            ->when($this->categoryIds, function (Builder $query) {
                return $query->whereIn('categories.id', $this->categoryIds);
            });
    }
}
