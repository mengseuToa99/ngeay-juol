<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\SubscriptionExpiringSoonNotification;
use App\Notifications\SubscriptionGraceEndingSoonNotification;
use App\Notifications\SubscriptionPastDueNotification;
use App\Services\SubscriptionService;
use App\Support\Notifications\NotificationDeduplicator;
use App\Support\Notifications\NotificationRecipients;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;

class ProcessSubscriptions extends Command
{
    protected $signature = 'subscriptions:process
        {--renew : Attempt automatic renewal for due subscriptions}
        {--bill-renewals : Ensure pending renewal payments exist for due subscriptions}
        {--sweep : Mark expired and past-grace subscriptions}
        {--recompute : Recompute unit counts for active subscriptions}
        {--dunning : Send subscription lifecycle reminders}';

    protected $description = 'Subscription lifecycle processing: expiry, grace, metering, dunning';

    public function handle(): int
    {
        $exitCode = 0;

        if ($this->option('renew') || ! $this->hasOptionSpecified()) {
            $renewals = SubscriptionService::autoRenewDue();
            $this->info("Auto-renew complete: {$renewals['renewed']} renewed, {$renewals['skipped']} skipped, {$renewals['failed']} failed.");

            foreach ($renewals['details'] as $detail) {
                if ($detail['status'] === 'renewed') {
                    continue;
                }

                $this->line(sprintf(
                    'Subscription %s %s via %s: %s',
                    $detail['subscription_id'],
                    $detail['status'],
                    $detail['gateway'],
                    $detail['reason'] ?? 'n/a',
                ));
            }
        }

        if ($this->option('bill-renewals') || ! $this->hasOptionSpecified()) {
            $created = SubscriptionService::ensurePendingRenewalPayments();
            $this->info("Pending renewal payments ensured: {$created} created.");
        }

        if ($this->option('sweep') || ! $this->hasOptionSpecified()) {
            $expired = SubscriptionService::markExpired();
            $revoked = SubscriptionService::purgeRevoked();
            $this->info("Marked {$expired} subscriptions as expired; {$revoked} subscriptions moved beyond retention.");
        }

        if ($this->option('recompute') || ! $this->hasOptionSpecified()) {
            SubscriptionService::recomputeAllUnitCounts();
            $this->info('Recomputed unit counts for all active subscriptions.');
        }

        if ($this->option('dunning') || ! $this->hasOptionSpecified()) {
            $this->sendDunningReminders();
        }

        return $exitCode;
    }

    private function sendDunningReminders(): void
    {
        $today = Carbon::today();

        $expiringSoon = Subscription::withoutGlobalScopes()
            ->with('plan')
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trial->value])
            ->where('ends_at', '>=', $today)
            ->where('ends_at', '<=', $today->copy()->addDays(7))
            ->get();

        $expiringSent = 0;
        foreach ($expiringSoon as $subscription) {
            $expiringSent += $this->sendStageReminder(
                $subscription,
                'expiring_soon',
                new SubscriptionExpiringSoonNotification($subscription),
            );
        }

        $this->info($expiringSoon->count()." subscriptions expiring within 7 days; sent {$expiringSent} notification(s).");

        $pastDue = Subscription::withoutGlobalScopes()
            ->with('plan')
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trial->value])
            ->where('ends_at', '<', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('grace_ends_at')
                    ->orWhere('grace_ends_at', '>=', $today);
            })
            ->get();

        $pastDueSent = 0;
        foreach ($pastDue as $subscription) {
            $pastDueSent += $this->sendStageReminder(
                $subscription,
                'past_due',
                new SubscriptionPastDueNotification($subscription),
            );
        }

        $this->info($pastDue->count()." subscriptions past due (in grace period); sent {$pastDueSent} notification(s).");

        $graceEndingSoon = Subscription::withoutGlobalScopes()
            ->with('plan')
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trial->value])
            ->where('ends_at', '<', $today)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '>=', $today)
            ->where('grace_ends_at', '<=', $today->copy()->addDays(2))
            ->get();

        $graceEndingSent = 0;
        foreach ($graceEndingSoon as $subscription) {
            $graceEndingSent += $this->sendStageReminder(
                $subscription,
                'grace_ending_soon',
                new SubscriptionGraceEndingSoonNotification($subscription),
            );
        }

        $this->info($graceEndingSoon->count()." subscriptions with grace ending within 2 days; sent {$graceEndingSent} notification(s).");
    }

    private function sendStageReminder(Subscription $subscription, string $stage, Notification $notification): int
    {
        $sent = 0;

        foreach (NotificationRecipients::landlordOperators((int) $subscription->landlord_id) as $user) {
            $sent += NotificationDeduplicator::sendOnce(
                $user,
                $notification,
                [
                    'subscription_id' => $subscription->id,
                    'reminder_stage' => $stage,
                ],
            ) ? 1 : 0;
        }

        return $sent;
    }

    private function hasOptionSpecified(): bool
    {
        return $this->option('renew')
            || $this->option('bill-renewals')
            || $this->option('sweep')
            || $this->option('recompute')
            || $this->option('dunning');
    }
}
