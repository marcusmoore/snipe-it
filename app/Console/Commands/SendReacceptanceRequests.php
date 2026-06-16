<?php

namespace App\Console\Commands;

use App\Actions\Acceptances\CreateCheckoutAcceptanceAction;
use App\Enums\ActionType;
use App\Mail\ReacceptanceRequestMail;
use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Consumable;
use App\Models\LicenseSeat;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendReacceptanceRequests extends Command
{
    private const TYPE_MAP = [
        'asset' => Asset::class,
        'license' => LicenseSeat::class,
        'accessory' => Accessory::class,
        'consumable' => Consumable::class,
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
        $morphClasses = $this->resolveTypeFilter();

        if ($morphClasses === null) {
            return self::FAILURE;
        }

        if (! $this->option('accepted-before')) {
            $this->warn('No --accepted-before given: users who already re-accepted may be prompted again once they accept. Pass --accepted-before to scope to a policy window.');
        }

        $candidates = $this->resolveCandidates($morphClasses);

        if ($candidates->isEmpty()) {
            $this->info('No items need re-acceptance.');

            return self::SUCCESS;
        }

        $byUser = $candidates->groupBy(fn (array $candidate) => $candidate['user']->id);
        $noEmailUsers = $byUser
            ->map(fn (Collection $group) => $group->first()['user'])
            ->filter(fn (User $user) => ! $user->email);

        $this->printPreview($candidates, $byUser, $noEmailUsers);

        if ($this->option('dry-run')) {
            $this->line('Dry run: nothing was written or sent. Run again with -v for the full per-user breakdown.');

            return self::SUCCESS;
        }

        if (! $this->validateSendOptions()) {
            return self::FAILURE;
        }

        $send = $this->resolveSendDecision($candidates->count(), $byUser->count());

        if ($send === null) {
            $this->info('Aborted. Nothing was written.');

            return self::SUCCESS;
        }

        $createdByUser = $this->regenerate($byUser);

        $notified = $send ? $this->sendEmails($createdByUser) : 0;

        $this->report($candidates->count(), $notified, $noEmailUsers);

        return self::SUCCESS;
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
     * Resolve the previously-accepted candidates still assigned to the same user.
     *
     * Returns a collection of ['user' => User, 'checkoutable' => Model, 'qty' => ?int,
     * 'acceptances' => Collection] arrays — one per (user, item).
     *
     * Future: an "all still-assigned" mode (never-accepted items) would plug in
     * here behind a --mode switch
     */
    private function resolveCandidates(array $morphClasses): Collection
    {
        $categories = $this->option('category');
        $company = $this->option('company');
        $acceptedBefore = $this->option('accepted-before') ? Carbon::parse($this->option('accepted-before')) : null;
        $userId = $this->option('user');

        $query = CheckoutAcceptance::accepted()
            ->notSuperseded()
            ->whereIn('checkoutable_type', $morphClasses)
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
                $morphClasses,
                fn ($itemQuery, string $type) => $this->applyItemFilters($itemQuery, $type, $categories, $company)
            );
        }

        return $query->get()
            ->filter(fn (CheckoutAcceptance $acceptance) => $acceptance->assignedTo
                && $this->stillAssignedToUser($acceptance->checkoutable, $acceptance->assignedTo))
            ->groupBy(fn (CheckoutAcceptance $acceptance) => $acceptance->assigned_to_id.'-'.$acceptance->checkoutable_type.'-'.$acceptance->checkoutable_id)
            ->map(function (Collection $group) {
                $latest = $group->sortByDesc('accepted_at')->first();

                return [
                    'user' => $latest->assignedTo,
                    'checkoutable' => $latest->checkoutable,
                    'qty' => $latest->qty,
                    'acceptances' => $group,
                ];
            })
            ->values();
    }

    /**
     * Apply the category/company filters for one morph type inside whereHasMorph.
     */
    private function applyItemFilters($itemQuery, string $type, array $categories, ?string $company): void
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
    private function regenerate(Collection $byUser): array
    {
        $createdByUser = [];

        foreach ($byUser as $userId => $candidates) {
            $created = collect();

            foreach ($candidates as $candidate) {
                $newAcceptance = DB::transaction(function () use ($candidate) {
                    $newAcceptance = CreateCheckoutAcceptanceAction::run(
                        $candidate['checkoutable'],
                        $candidate['user'],
                        $candidate['qty'],
                    );

                    foreach ($candidate['acceptances'] as $supersededAcceptance) {
                        $supersededAcceptance->markSupersededBy($newAcceptance);
                    }

                    $this->logReacceptanceRequested($newAcceptance, auth()?->id());

                    return $newAcceptance;
                });

                $created->push($newAcceptance);
            }

            $createdByUser[$userId] = [
                'user' => $candidates->first()['user'],
                'acceptances' => $created,
            ];
        }

        return $createdByUser;
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
     * @param  array<int, array{user: User, acceptances: Collection}>  $createdByUser
     */
    private function sendEmails(array $createdByUser): int
    {
        $notified = 0;

        foreach ($createdByUser as $entry) {
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

        if ($this->confirm('Send the re-acceptance emails now?', true)) {
            return true;
        }

        $this->warn('These users will NOT be notified now. You can send the generic reminder later with `snipeit:acceptance-reminder` (which uses the old reminder wording, not the re-accept wording).');

        return $this->confirm('Continue and create the acceptances WITHOUT sending emails?', false) ? false : null;
    }

    private function printPreview(Collection $candidates, Collection $byUser, Collection $noEmailUsers): void
    {
        $supersededCount = $candidates->sum(fn (array $candidate) => $candidate['acceptances']->count());

        $this->info("Would regenerate {$candidates->count()} acceptances for {$byUser->count()} users (superseding {$supersededCount} existing acceptances).");

        if ($noEmailUsers->isNotEmpty()) {
            $this->warn("{$noEmailUsers->count()} of these users have no email address and will not be notified:");
            $this->table(['ID', 'Name'], $noEmailUsers->map(fn (User $user) => [$user->id, $user->display_name])->all());
        }

        if ($this->output->isVerbose()) {
            foreach ($byUser as $candidatesForUser) {
                $user = $candidatesForUser->first()['user'];
                $this->line("- {$user->display_name} (#{$user->id}):");
                foreach ($candidatesForUser as $candidate) {
                    $this->line('    '.class_basename($candidate['checkoutable']).' #'.$candidate['checkoutable']->id);
                }
            }
        }
    }

    private function report(int $regenerated, int $notified, Collection $noEmailUsers): void
    {
        $this->info("Regenerated {$regenerated} acceptances. Notified {$notified} users.");

        if ($noEmailUsers->isNotEmpty()) {
            $this->warn("Skipped {$noEmailUsers->count()} users with no email address:");
            $this->table(['ID', 'Name'], $noEmailUsers->map(fn (User $user) => [$user->id, $user->display_name])->all());
        }
    }
}
