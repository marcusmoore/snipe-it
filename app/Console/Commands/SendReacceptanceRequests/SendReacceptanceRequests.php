<?php

namespace App\Console\Commands\SendReacceptanceRequests;

use App\Actions\Acceptances\CreateCheckoutAcceptanceAction;
use App\Enums\ActionType;
use App\Mail\ReacceptanceRequestMail;
use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CheckoutAcceptance;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\LicenseSeat;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multisearch;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

class SendReacceptanceRequests extends Command
{
    private const TYPE_MAP = [
        'asset' => Asset::class,
        'license' => LicenseSeat::class,
        'accessory' => Accessory::class,
        'consumable' => Consumable::class,
    ];

    /**
     * Map each covered morph class to the category_type used to scope its
     * categories. Re-acceptance never covers components.
     */
    private const MORPH_CATEGORY_TYPE = [
        Asset::class => 'asset',
        LicenseSeat::class => 'license',
        Accessory::class => 'accessory',
        Consumable::class => 'consumable',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snipeit:send-reacceptance-requests
        {--category=* : Only items in these category id(s)}
        {--company= : Only items belonging to this company id}
        {--accepted-before= : Only acceptances accepted before this date (Y-m-d)}
        {--type=* : Limit to checkoutable type(s): asset, license, accessory, consumable}
        {--user= : Only items currently assigned to this user id}
        {--dry-run : Preview only; writes nothing and sends nothing}
        {--force : Skip the regenerate confirmation (required for non-interactive runs)}
        {--send : Send the re-acceptance emails (non-interactive runs)}
        {--no-send : Do not send emails (non-interactive runs)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate fresh acceptance requests for previously-accepted items still assigned to the same user, and (optionally) email those users.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filters = $this->buildFiltersFromOptions();

        if ($filters === null) {
            return self::FAILURE;
        }

        if ($this->input->isInteractive()) {
            $filters = $this->collectFiltersInteractively($filters);
        }

        if (! $filters->acceptedBefore) {
            $this->warn('No accepted-before cutoff in effect: users who already re-accepted may be prompted again once they accept. Set a cutoff to scope to a smaller window of time.');
        }

        $candidates = $this->resolveCandidates($filters);

        if ($candidates->isEmpty()) {
            $this->info('No items need re-acceptance.');

            return self::SUCCESS;
        }

        $candidatesByUser = $candidates->groupBy(fn (Candidate $candidate) => $candidate->user->id);
        $noEmailUsers = $candidatesByUser
            ->map(fn (Collection $group) => $group->first()->user)
            ->filter(fn (User $user) => ! $user->email);

        $this->printPreview($candidates, $candidatesByUser, $noEmailUsers);

        if ($this->wantsItemBreakdown()) {
            $this->printItemBreakdown($candidatesByUser);
        }

        if ($this->resolveDryRun()) {
            $this->line('Dry run: nothing was written or sent.');

            if (! $this->output->isVerbose() && ! $this->input->isInteractive()) {
                $this->line('Run again with -v for the full per-user breakdown.');
            }

            return self::SUCCESS;
        }

        if (! $this->validateSendOptions()) {
            return self::FAILURE;
        }

        $send = $this->resolveSendDecision($candidates->count(), $candidatesByUser->count());

        if ($send === null) {
            $this->info('Aborted. Nothing was written.');

            return self::SUCCESS;
        }

        $createdAcceptancesByUser = $this->regenerateAcceptances($candidatesByUser);

        $notified = $send ? $this->sendEmails($createdAcceptancesByUser) : 0;

        $this->printFinalResults($candidates->count(), $notified, $noEmailUsers);

        return self::SUCCESS;
    }

    /**
     * Build the filters from the CLI options. Returns null when the --type
     * tokens are invalid (resolveTypeFilter() already printed the error), so the
     * caller can return self::FAILURE.
     */
    private function buildFiltersFromOptions(): ?ReacceptanceFilters
    {
        $morphClasses = $this->resolveTypeFilter();

        if ($morphClasses === null) {
            return null;
        }

        return new ReacceptanceFilters(
            types: $morphClasses,
            categories: array_map('intval', $this->option('category')),
            company: $this->option('company') ? (int) $this->option('company') : null,
            user: $this->option('user') ? (int) $this->option('user') : null,
            acceptedBefore: $this->option('accepted-before') ? Carbon::parse($this->option('accepted-before')) : null,
        );
    }

    /**
     * Map and validate the --type tokens to morph classes. Returns null on an
     * invalid token (after printing an error); otherwise the list of morph
     * classes to consider (all four covered types when --type is omitted).
     */
    private function resolveTypeFilter(): ?array
    {
        $tokens = $this->option('type');

        if (empty($tokens)) {
            return array_values(self::TYPE_MAP);
        }

        $morphClasses = [];
        foreach ($tokens as $token) {
            if (! isset(self::TYPE_MAP[$token])) {
                $this->error("Unknown --type '{$token}'. Valid types: ".implode(', ', array_keys(self::TYPE_MAP)).'.');

                return null;
            }
            $morphClasses[] = self::TYPE_MAP[$token];
        }

        return $morphClasses;
    }

    /**
     * Walk the user through the filters interactively, prompting only for those
     * NOT already supplied as a flag. A passed flag skips its prompt and keeps
     * the value already resolved in $fromOptions.
     */
    private function collectFiltersInteractively(ReacceptanceFilters $fromOptions): ReacceptanceFilters
    {
        $types = empty($this->option('type'))
            ? $this->promptForTypes()
            : $fromOptions->types;

        $categories = empty($this->option('category'))
            ? $this->promptForCategories($types)
            : $fromOptions->categories;

        $company = $this->option('company')
            ? $fromOptions->company
            : $this->promptForCompany();

        $user = $this->option('user')
            ? $fromOptions->user
            : $this->promptForUser();

        $acceptedBefore = $this->option('accepted-before')
            ? $fromOptions->acceptedBefore
            : $this->promptForAcceptedBefore();

        return new ReacceptanceFilters(
            types: $types,
            categories: $categories,
            company: $company,
            user: $user,
            acceptedBefore: $acceptedBefore,
        );
    }

    /**
     * Prompt for the covered checkoutable type(s). An empty selection is treated
     * as all four covered types.
     *
     * @return string[] the selected morph classes
     */
    private function promptForTypes(): array
    {
        $tokens = array_keys(self::TYPE_MAP);

        $selected = multiselect(
            label: 'Which item types would you like to regenerate acceptances for?',
            options: [
                'asset' => 'Assets',
                'license' => 'Licenses',
                'accessory' => 'Accessories',
                'consumable' => 'Consumables',
            ],
            default: $tokens,
            hint: 'All types are selected by default. Deselect any you want to skip.',
        );

        if (empty($selected)) {
            return array_values(self::TYPE_MAP);
        }

        return array_map(fn (string $token) => self::TYPE_MAP[$token], $selected);
    }

    /**
     * Optionally narrow to specific categories via a gate confirm + multisearch,
     * scoped to the category_type(s) of the selected morph classes. Declining the
     * gate applies no category filter.
     *
     * @param  string[]  $types  the selected morph classes
     * @return int[] the selected category ids
     */
    private function promptForCategories(array $types): array
    {
        if (! confirm(label: 'Limit to specific categories?', default: false)) {
            return [];
        }

        $categoryTypes = array_map(fn (string $morphClass) => self::MORPH_CATEGORY_TYPE[$morphClass], $types);

        $selected = multisearch(
            label: 'Search for categories to include.',
            options: function (string $value) use ($categoryTypes): array {
                $query = Category::whereIn('category_type', $categoryTypes)->orderBy('name');

                if ($value !== '') {
                    $query->where('name', 'like', "%{$value}%");
                }

                return $query->get()
                    ->mapWithKeys(fn (Category $category) => [
                        $category->id => "{$category->name} ({$category->category_type})",
                    ])
                    ->toArray();
            },
            placeholder: 'Type to search categories...',
            scroll: 10,
        );

        return array_map('intval', $selected);
    }

    /**
     * Optionally narrow to a single company via a gate confirm + search.
     */
    private function promptForCompany(): ?int
    {
        if (! confirm(label: 'Filter to a specific company?', default: false)) {
            return null;
        }

        $companyId = search(
            label: 'Search for a company by name.',
            options: function (string $value): array {
                if ($value === '') {
                    return [];
                }

                return Company::where('name', 'like', "%{$value}%")
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (Company $company) => [$company->id => "{$company->name} (ID: {$company->id})"])
                    ->toArray();
            },
            placeholder: 'Type to search companies...',
        );

        return $companyId ? (int) $companyId : null;
    }

    /**
     * Optionally narrow to a single user via a gate confirm + search.
     */
    private function promptForUser(): ?int
    {
        if (! confirm(label: 'Limit to a single user?', default: false)) {
            return null;
        }

        $userId = search(
            label: 'Search for a user by username, first or last name.',
            options: function (string $value): array {
                if ($value === '') {
                    return [];
                }

                return User::where(function ($query) use ($value) {
                    $query->where('username', 'like', "%{$value}%")
                        ->orWhere('first_name', 'like', "%{$value}%")
                        ->orWhere('last_name', 'like', "%{$value}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"]);
                })
                    ->get()
                    ->mapWithKeys(fn (User $user) => [$user->id => "{$user->first_name} {$user->last_name} ({$user->username})"])
                    ->toArray();
            },
            placeholder: 'Type to search users...',
        );

        return $userId ? (int) $userId : null;
    }

    /**
     * Optionally set an accepted-before cutoff via a gate confirm + validated
     * date text (parseable Y-m-d, not in the future).
     */
    private function promptForAcceptedBefore(): ?Carbon
    {
        if (! confirm(label: 'Only include items accepted before a cutoff date?', default: false)) {
            return null;
        }

        $value = text(
            label: 'Accepted-before cutoff date (Y-m-d):',
            placeholder: 'e.g. '.now()->format('Y-m-d'),
            validate: function (string $value): ?string {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $value);
                } catch (Exception $e) {
                    return 'Enter a valid date in Y-m-d format.';
                }

                if ($date === false || $date->format('Y-m-d') !== $value) {
                    return 'Enter a valid date in Y-m-d format.';
                }

                if ($date->isFuture()) {
                    return 'The cutoff date cannot be in the future.';
                }

                return null;
            },
        );

        return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
    }

    /**
     * Resolve the previously-accepted candidates still assigned to the same user.
     *
     * Returns a Collection<Candidate> — one per (user, item).
     *
     * Future: an "all still-assigned" mode (never-accepted items) would plug in
     * here behind a --mode switch
     */
    private function resolveCandidates(ReacceptanceFilters $filters): Collection
    {
        $categories = $filters->categories;
        $company = $filters->company;
        $acceptedBefore = $filters->acceptedBefore;
        $userId = $filters->user;

        $query = CheckoutAcceptance::accepted()
            ->notSuperseded()
            ->whereIn('checkoutable_type', $filters->types)
            ->with([
                'assignedTo',
                'checkoutable' => function (MorphTo $morph) {
                    $morph->morphWith([
                        Asset::class => ['model'],
                        Accessory::class => ['checkouts'],
                        Consumable::class => ['users'],
                        LicenseSeat::class => ['license'],
                    ]);
                },
            ]);

        if ($acceptedBefore) {
            $query->where('accepted_at', '<', $acceptedBefore);
        }

        if ($userId) {
            $query->where('assigned_to_id', $userId);
        }

        if (! empty($categories) || $company) {
            $query->whereHasMorph(
                'checkoutable',
                $filters->types,
                fn ($itemQuery, string $type) => $this->applyItemFilters($itemQuery, $type, $categories, $company)
            );
        }

        return $query->get()
            ->filter(fn (CheckoutAcceptance $acceptance) => $acceptance->assignedTo
                && $this->stillAssignedToUser($acceptance->checkoutable, $acceptance->assignedTo))
            ->groupBy(fn (CheckoutAcceptance $acceptance) => $acceptance->assigned_to_id.'-'.$acceptance->checkoutable_type.'-'.$acceptance->checkoutable_id)
            ->map(function (Collection $group) {
                $latest = $group->sortByDesc('accepted_at')->first();

                return new Candidate(
                    user: $latest->assignedTo,
                    checkoutable: $latest->checkoutable,
                    qty: $this->resolveGroupQuantity($group, $latest),
                    acceptances: $group,
                );
            })
            ->values();
    }

    /**
     * Apply the category/company filters for one morph type inside whereHasMorph.
     */
    private function applyItemFilters($itemQuery, string $type, array $categories, ?int $company): void
    {
        if (! empty($categories)) {
            match ($type) {
                Asset::class => $itemQuery->whereHas('model', fn ($modelQuery) => $modelQuery->whereIn('category_id', $categories)),
                LicenseSeat::class => $itemQuery->whereHas('license', fn ($licenseQuery) => $licenseQuery->whereIn('category_id', $categories)),
                default => $itemQuery->whereIn('category_id', $categories),
            };
        }

        if ($company) {
            match ($type) {
                LicenseSeat::class => $itemQuery->whereHas('license', fn ($licenseQuery) => $licenseQuery->where('company_id', $company)),
                default => $itemQuery->where('company_id', $company),
            };
        }
    }

    /**
     * Resolve the quantity for the regenerated acceptance from a group of prior
     * accepted acceptances (same user + item).
     *
     * Consumables and accessories accumulate: a user may hold several checkouts
     * of the same item, so the held quantity is the sum across the group. Assets
     * and license seats are single-unit holdings — duplicate accepted rows are an
     * anomaly, not accumulation — so the latest row's quantity is carried forward.
     */
    private function resolveGroupQuantity(Collection $group, CheckoutAcceptance $latest): ?int
    {
        $checkoutable = $latest->checkoutable;

        if ($checkoutable instanceof Consumable || $checkoutable instanceof Accessory) {
            return $group->sum(fn (CheckoutAcceptance $acceptance) => $acceptance->qty ?? 1);
        }

        return $latest->qty;
    }

    /**
     * Is the checkoutable currently still assigned to this user?
     */
    private function stillAssignedToUser($checkoutable, User $user): bool
    {
        return match (true) {
            $checkoutable instanceof Asset => $checkoutable->checkedOutToUser() && (int) $checkoutable->assigned_to === $user->id,
            $checkoutable instanceof LicenseSeat => (int) $checkoutable->assigned_to === $user->id,
            $checkoutable instanceof Accessory => $checkoutable->checkouts
                ->where('assigned_type', User::class)
                ->where('assigned_to', $user->id)
                ->isNotEmpty(),
            $checkoutable instanceof Consumable => $checkoutable->users->contains('id', $user->id),
            default => false,
        };
    }

    /**
     * Regenerate acceptances: per item, create the fresh pending row, supersede
     * the prior accepted row(s), and log it — all atomically.
     *
     * @return array<int, array{user: User, acceptances: Collection}>
     */
    private function regenerateAcceptances(Collection $candidatesByUser): array
    {
        $createdAcceptancesByUser = [];

        foreach ($candidatesByUser as $userId => $candidates) {
            $created = collect();

            foreach ($candidates as $candidate) {
                $newAcceptance = DB::transaction(function () use ($candidate) {
                    $newAcceptance = CreateCheckoutAcceptanceAction::run(
                        $candidate->checkoutable,
                        $candidate->user,
                        $candidate->qty,
                    );

                    foreach ($candidate->acceptances as $supersededAcceptance) {
                        $supersededAcceptance->markSupersededBy($newAcceptance);
                    }

                    $this->logReacceptanceRequested($newAcceptance, auth()?->id());

                    return $newAcceptance;
                });

                $created->push($newAcceptance);
            }

            $createdAcceptancesByUser[$userId] = [
                'user' => $candidates->first()->user,
                'acceptances' => $created,
            ];
        }

        return $createdAcceptancesByUser;
    }

    /**
     * Write the single re-acceptance-requested action log for a regenerated item.
     * Mirrors LogListener::onCheckoutAccepted but is null-safe for the CLI actor
     * and writes no signature/EULA file.
     */
    private function logReacceptanceRequested(CheckoutAcceptance $acceptance, ?int $initiatedById): void
    {
        $checkoutable = $acceptance->checkoutable;

        $logaction = new Actionlog;
        // Mirror LogListener: a license seat is logged against its parent license.
        $logaction->item()->associate($checkoutable instanceof LicenseSeat ? $checkoutable->license : $checkoutable);
        $logaction->target()->associate($acceptance->assignedTo);
        $logaction->action_type = ActionType::ReacceptanceRequested->value;
        $logaction->quantity = $acceptance->qty ?? 1;
        $logaction->created_by = $initiatedById;
        $logaction->save();
    }

    /**
     * Send one email per user with their set of new acceptances, skipping users
     * without an email address. Returns the number of users notified.
     *
     * @param  array<int, array{user: User, acceptances: Collection}>  $createdAcceptancesByUser
     */
    private function sendEmails(array $createdAcceptancesByUser): int
    {
        $notified = 0;

        foreach ($createdAcceptancesByUser as $entry) {
            $user = $entry['user'];

            if (! $user->email) {
                continue;
            }

            $mailable = new ReacceptanceRequestMail($user, $entry['acceptances']);
            $locale = $user->locale;

            $locale
                ? Mail::to($user->email)->send($mailable->locale($locale))
                : Mail::to($user->email)->send($mailable);

            $notified++;
        }

        return $notified;
    }

    /**
     * Validate the non-interactive send options. Interactive runs prompt instead,
     * so they always pass. Prints an error and returns false on a bad combo.
     */
    private function validateSendOptions(): bool
    {
        if ($this->input->isInteractive()) {
            return true;
        }

        if (! $this->option('force')) {
            $this->error('Non-interactive run: pass --force to skip the regenerate confirmation.');

            return false;
        }

        if ($this->option('send') && $this->option('no-send')) {
            $this->error('--send and --no-send cannot be used together.');

            return false;
        }

        if (! $this->option('send') && ! $this->option('no-send')) {
            $this->error('Non-interactive run: pass --send or --no-send to choose the email behavior.');

            return false;
        }

        return true;
    }

    /**
     * Decide whether this is a dry run: honored from --dry-run, else prompted on
     * interactive runs (defaulting to true), else false.
     */
    private function resolveDryRun(): bool
    {
        if ($this->option('dry-run')) {
            return true;
        }

        if ($this->input->isInteractive()) {
            return confirm(label: 'Is this a dry run?', default: true);
        }

        return false;
    }

    /**
     * Decide whether to send emails.
     *
     * @return bool|null true = send emails, false = create without sending, null = user aborted
     */
    private function resolveSendDecision(int $count, int $userCount): ?bool
    {
        if (! $this->input->isInteractive()) {
            return (bool) $this->option('send');
        }

        if (! $this->confirm("Regenerate {$count} acceptances for {$userCount} users?")) {
            return null;
        }

        // A passed --send/--no-send flag skips the send prompt and is honored.
        if ($this->option('send')) {
            return true;
        }

        if ($this->option('no-send')) {
            return false;
        }

        if ($this->confirm('Send the re-acceptance emails now?', true)) {
            return true;
        }

        $this->warn('These users will NOT be notified now. You can send the generic reminder later with `snipeit:acceptance-reminder` (which uses the old reminder wording, not the re-accept wording).');

        return $this->confirm('Continue and create the acceptances WITHOUT sending emails?', false) ? false : null;
    }

    private function printPreview(Collection $candidates, Collection $candidatesByUser, Collection $noEmailUsers): void
    {
        $supersededCount = $candidates->sum(fn (Candidate $candidate) => $candidate->acceptances->count());

        $this->info("Would regenerate {$candidates->count()} acceptances for {$candidatesByUser->count()} users (superseding {$supersededCount} existing acceptances).");

        if ($noEmailUsers->isNotEmpty()) {
            $this->warn("{$noEmailUsers->count()} of these users have no email address and will not be notified:");
            $this->printUsersTable($noEmailUsers);
        }
    }

    /**
     * Decide whether to print the per-user/item breakdown. Interactive runs are
     * always offered the breakdown (defaulting to the -v flag); non-interactive
     * runs print it only when -v was passed.
     */
    private function wantsItemBreakdown(): bool
    {
        if ($this->input->isInteractive()) {
            return confirm(label: 'Show a breakdown of the affected users and items?', default: $this->output->isVerbose());
        }

        return $this->output->isVerbose();
    }

    /**
     * Print one line per user with each of their affected items.
     */
    private function printItemBreakdown(Collection $candidatesByUser): void
    {
        foreach ($candidatesByUser as $candidatesForUser) {
            $user = $candidatesForUser->first()->user;
            $this->line("- {$user->display_name} (#{$user->id}):");
            foreach ($candidatesForUser as $candidate) {
                $this->line('    '.class_basename($candidate->checkoutable).' #'.$candidate->checkoutable->id);
            }
        }
    }

    private function printFinalResults(int $regenerated, int $notified, Collection $noEmailUsers): void
    {
        $this->info("Regenerated {$regenerated} acceptances. Notified {$notified} users.");

        if ($noEmailUsers->isNotEmpty()) {
            $this->warn("Skipped {$noEmailUsers->count()} users with no email address:");
            $this->printUsersTable($noEmailUsers);
        }
    }

    /**
     * Render an ID/Name console table for a collection of users.
     *
     * @param  Collection<int, User>  $users
     */
    private function printUsersTable(Collection $users): void
    {
        $this->table(['ID', 'Name'], $users->map(fn (User $user) => [$user->id, $user->display_name])->all());
    }
}
