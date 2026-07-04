<?php

namespace Tests\Feature;

use App\Contracts\Billing\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PlanBillingModel;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\SubscriptionExpiringSoonNotification;
use App\Notifications\SubscriptionGraceEndingSoonNotification;
use App\Services\SubscriptionService;
use App\Support\Billing\GatewayChargeResult;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;

    protected SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->landlord = User::factory()->create(['email' => 'landlord@example.com']);
        $this->landlord->assignRole('landlord');

        $this->plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'billing_model' => PlanBillingModel::Flat,
            'interval' => PlanInterval::Monthly,
            'price' => 19,
            'currency' => 'USD',
            'grace_days' => 7,
        ]);
    }

    public function test_dunning_reminders_are_deduplicated_by_subscription_and_stage(): void
    {
        Carbon::setTestNow('2026-07-03 09:00:00');

        $subscription = $this->createSubscription([
            'starts_at' => '2026-06-06',
            'ends_at' => '2026-07-06',
            'grace_ends_at' => '2026-07-13',
        ]);

        Artisan::call('subscriptions:process', ['--dunning' => true]);

        Carbon::setTestNow('2026-07-04 09:00:00');
        Artisan::call('subscriptions:process', ['--dunning' => true]);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'type' => SubscriptionExpiringSoonNotification::class,
            'notifiable_type' => $this->landlord->getMorphClass(),
            'notifiable_id' => $this->landlord->id,
        ]);

        $notification = $this->landlord->notifications()->first();

        $this->assertSame($subscription->id, $notification->data['subscription_id']);
        $this->assertSame('expiring_soon', $notification->data['reminder_stage']);

        Carbon::setTestNow();
    }

    public function test_grace_ending_soon_dunning_stage_is_sent_once(): void
    {
        Carbon::setTestNow('2026-07-03 09:00:00');

        $subscription = $this->createSubscription([
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-06-30',
            'grace_ends_at' => '2026-07-05',
        ]);

        Artisan::call('subscriptions:process', ['--dunning' => true]);
        Artisan::call('subscriptions:process', ['--dunning' => true]);

        $this->assertDatabaseHas('notifications', [
            'type' => SubscriptionGraceEndingSoonNotification::class,
            'notifiable_type' => $this->landlord->getMorphClass(),
            'notifiable_id' => $this->landlord->id,
        ]);

        $this->assertSame(
            'grace_ending_soon',
            $this->landlord->notifications()
                ->where('type', SubscriptionGraceEndingSoonNotification::class)
                ->first()
                ->data['reminder_stage'],
        );

        $this->assertSame($subscription->id, $this->landlord->notifications()
            ->where('type', SubscriptionGraceEndingSoonNotification::class)
            ->first()
            ->data['subscription_id']);

        Carbon::setTestNow();
    }

    public function test_failed_auto_renew_records_failed_payment_without_mutating_subscription(): void
    {
        Carbon::setTestNow('2026-07-03 09:00:00');

        $subscription = $this->createSubscription([
            'starts_at' => '2026-06-03',
            'ends_at' => '2026-07-03',
            'grace_ends_at' => '2026-07-10',
            'auto_renew' => true,
        ]);

        $result = SubscriptionService::autoRenewDue(new class implements PaymentGateway
        {
            public function key(): string
            {
                return 'test_gateway';
            }

            public function supportsAutoRenew(Subscription $subscription): bool
            {
                return true;
            }

            public function chargeSubscription(Subscription $subscription): GatewayChargeResult
            {
                return GatewayChargeResult::failed('Card declined.');
            }
        });

        $subscription->refresh();

        $this->assertSame(0, $result['renewed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('2026-07-03', $subscription->ends_at->toDateString());
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertDatabaseHas('subscription_payments', [
            'subscription_id' => $subscription->id,
            'status' => SubscriptionPaymentStatus::Failed->value,
            'gateway' => 'test_gateway',
        ]);

        Carbon::setTestNow();
    }

    public function test_successful_auto_renew_creates_payment_and_extends_subscription(): void
    {
        Carbon::setTestNow('2026-07-03 09:00:00');

        $subscription = $this->createSubscription([
            'starts_at' => '2026-06-03',
            'ends_at' => '2026-07-03',
            'grace_ends_at' => '2026-07-10',
            'auto_renew' => true,
        ]);

        $result = SubscriptionService::autoRenewDue(new class implements PaymentGateway
        {
            public function key(): string
            {
                return 'test_gateway';
            }

            public function supportsAutoRenew(Subscription $subscription): bool
            {
                return true;
            }

            public function chargeSubscription(Subscription $subscription): GatewayChargeResult
            {
                return GatewayChargeResult::success('txn_123', 'ref_123');
            }
        });

        $subscription->refresh();

        $this->assertSame(1, $result['renewed']);
        $this->assertSame('2026-08-03', $subscription->ends_at->toDateString());
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertDatabaseHas('subscription_payments', [
            'subscription_id' => $subscription->id,
            'status' => SubscriptionPaymentStatus::Succeeded->value,
            'gateway' => 'test_gateway',
            'gateway_transaction_id' => 'txn_123',
        ]);

        Carbon::setTestNow();
    }

    public function test_manual_subscription_renewal_flow_still_works(): void
    {
        Carbon::setTestNow('2026-07-03 09:00:00');

        $subscription = $this->createSubscription([
            'starts_at' => '2026-06-03',
            'ends_at' => '2026-07-03',
            'grace_ends_at' => '2026-07-10',
        ]);

        $payment = SubscriptionService::renew($subscription, [
            'amount' => 19,
            'method' => PaymentMethod::BankTransfer,
            'gateway' => 'manual',
        ]);

        $subscription->refresh();

        $this->assertSame(SubscriptionPaymentStatus::Succeeded, $payment->status);
        $this->assertSame('manual', $payment->gateway);
        $this->assertSame('2026-08-03', $subscription->ends_at->toDateString());
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);

        Carbon::setTestNow();
    }

    /** @param array<string, mixed> $overrides */
    private function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'landlord_id' => $this->landlord->id,
            'plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_model' => PlanBillingModel::Flat,
            'interval' => PlanInterval::Monthly,
            'price' => 19,
            'currency' => 'USD',
            'starts_at' => '2026-06-03',
            'ends_at' => '2026-07-03',
            'grace_ends_at' => '2026-07-10',
            'auto_renew' => true,
        ], $overrides));
    }
}
