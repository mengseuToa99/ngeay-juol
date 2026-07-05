<?php

namespace Tests\Feature;

use App\Contracts\Billing\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PlanBillingModel;
use App\Enums\PlanInterval;
use App\Enums\RentalStatus;
use App\Enums\SubscriptionAccess;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UnitStatus;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Rental;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Billing\GatewayChargeResult;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LandlordSubscriptionExpiryBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
    }

    public function test_due_manual_subscription_creates_exactly_one_pending_payment_and_scheduler_is_idempotent(): void
    {
        Carbon::setTestNow('2026-07-03 09:00:00');

        $subscription = $this->createSubscription([
            'auto_renew' => false,
            'ends_at' => '2026-07-03',
            'grace_ends_at' => '2026-07-10',
        ]);

        Artisan::call('subscriptions:process', ['--bill-renewals' => true]);
        Artisan::call('subscriptions:process', ['--bill-renewals' => true]);

        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionPaymentStatus::Pending->value)
            ->count());

        $payment = SubscriptionPayment::withoutGlobalScopes()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionPaymentStatus::Pending->value)
            ->firstOrFail();

        $this->assertSame('manual', $payment->gateway);
        $this->assertSame(PaymentMethod::BankTransfer, $payment->method);
        $this->assertSame('2026-07-03', $payment->covers_from->toDateString());
        $this->assertSame('2026-08-03', $payment->covers_to->toDateString());

        Carbon::setTestNow();
    }

    public function test_successful_auto_renew_creates_succeeded_payment_and_no_pending_payment(): void
    {
        Carbon::setTestNow('2026-07-03 09:00:00');

        $subscription = $this->createSubscription([
            'auto_renew' => true,
            'ends_at' => '2026-07-03',
            'grace_ends_at' => '2026-07-10',
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
                return GatewayChargeResult::success('txn_success', 'ref_success');
            }
        });

        $subscription->refresh();

        $this->assertSame(1, $result['renewed']);
        $this->assertSame('2026-08-03', $subscription->ends_at->toDateString());
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionPaymentStatus::Succeeded->value)
            ->count());
        $this->assertSame(0, SubscriptionPayment::withoutGlobalScopes()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionPaymentStatus::Pending->value)
            ->count());

        Carbon::setTestNow();
    }

    public function test_failed_auto_renew_keeps_subscription_dates_and_pending_payment_is_created(): void
    {
        Carbon::setTestNow('2026-07-03 09:00:00');

        $subscription = $this->createSubscription([
            'auto_renew' => true,
            'ends_at' => '2026-07-03',
            'grace_ends_at' => '2026-07-10',
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

        $pending = SubscriptionService::ensurePendingRenewalPayment($subscription);

        $this->assertNotNull($pending);
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionPaymentStatus::Failed->value)
            ->count());
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionPaymentStatus::Pending->value)
            ->count());

        Carbon::setTestNow();
    }

    public function test_effective_access_moves_past_due_read_only_then_revoked(): void
    {
        $landlord = $this->createLandlord();
        $this->createPlan();

        $subscription = Subscription::withoutGlobalScopes()->create([
            'landlord_id' => $landlord->id,
            'plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_model' => PlanBillingModel::Tiered,
            'interval' => PlanInterval::Monthly,
            'price' => 30,
            'currency' => 'USD',
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-07-01',
            'grace_ends_at' => '2026-07-08',
            'auto_renew' => true,
        ]);

        Carbon::setTestNow('2026-07-04 09:00:00');
        $this->assertSame(\App\Enums\SubscriptionAccess::PastDue, SubscriptionService::effectiveAccess($landlord));

        Carbon::setTestNow('2026-07-10 09:00:00');
        $this->assertSame(\App\Enums\SubscriptionAccess::ReadOnly, SubscriptionService::effectiveAccess($landlord));

        Carbon::setTestNow('2026-10-10 09:00:00');
        $this->assertSame(\App\Enums\SubscriptionAccess::Revoked, SubscriptionService::effectiveAccess($landlord));

        Carbon::setTestNow();
    }

    public function test_tenant_portal_routes_remain_accessible_even_when_landlord_subscription_is_revoked(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $landlord = $this->createLandlord();
        $tenant = $this->createTenant();
        $plan = $this->createPlan();

        Subscription::withoutGlobalScopes()->create([
            'landlord_id' => $landlord->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_model' => PlanBillingModel::Tiered,
            'interval' => PlanInterval::Monthly,
            'price' => 30,
            'currency' => 'USD',
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-02-01',
            'grace_ends_at' => '2026-02-08',
            'auto_renew' => false,
        ]);

        $this->assertSame(SubscriptionAccess::Revoked, SubscriptionService::effectiveAccess($landlord));

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Portal Property',
        ]);

        $unit = Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'account_user_id' => $tenant->id,
            'room_number' => 'A-101',
            'room_type' => 'Standard',
            'rent_amount' => 300,
            'status' => UnitStatus::Occupied,
        ]);

        $rental = Rental::create([
            'unit_id' => $unit->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'occupant_name' => 'Tenant Person',
            'monthly_rent' => 300,
            'status' => RentalStatus::Active,
            'start_date' => '2026-06-01',
            'next_invoice_date' => '2026-07-01',
        ]);

        Invoice::create([
            'rental_id' => $rental->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-001',
            'amount_due' => 300,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'payment_status' => \App\Enums\InvoiceStatus::Pending,
        ]);

        $this->actingAs($tenant);

        $this->get(route('portal.dashboard'))->assertOk();
        $this->get(route('portal.invoice', Invoice::first()))->assertOk();

        Carbon::setTestNow();
    }

    public function test_admin_marking_pending_payment_as_succeeded_renews_once_and_is_idempotent(): void
    {
        Carbon::setTestNow('2026-07-03 09:00:00');

        $subscription = $this->createSubscription([
            'auto_renew' => false,
            'ends_at' => '2026-07-03',
            'grace_ends_at' => '2026-07-10',
        ]);

        $pending = SubscriptionService::ensurePendingRenewalPayment($subscription);

        $originalEndsAt = $subscription->fresh()->ends_at->toDateString();

        $renewed = SubscriptionService::renew($subscription, [
            'subscription_id' => $subscription->id,
            'amount' => $pending->amount,
            'currency' => $pending->currency,
            'method' => $pending->method,
            'status' => SubscriptionPaymentStatus::Succeeded,
            'paid_at' => now(),
            'covers_from' => $pending->covers_from->toDateString(),
            'covers_to' => $pending->covers_to->toDateString(),
            'gateway' => 'manual',
            'receipt_number' => $pending->receipt_number,
        ]);

        $subscription->refresh();

        $this->assertSame(SubscriptionPaymentStatus::Succeeded, $renewed->status);
        $this->assertSame('2026-08-03', $subscription->ends_at->toDateString());
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionPaymentStatus::Succeeded->value)
            ->count());

        SubscriptionService::renew($subscription, [
            'subscription_id' => $subscription->id,
            'amount' => $pending->amount,
            'currency' => $pending->currency,
            'method' => $pending->method,
            'status' => SubscriptionPaymentStatus::Succeeded,
            'paid_at' => now()->addMinute(),
            'covers_from' => $pending->covers_from->toDateString(),
            'covers_to' => $pending->covers_to->toDateString(),
            'gateway' => 'manual',
            'receipt_number' => $pending->receipt_number,
        ]);

        $subscription->refresh();

        $this->assertSame('2026-08-03', $subscription->ends_at->toDateString());
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionPaymentStatus::Succeeded->value)
            ->count());
        $this->assertSame('2026-07-03', $originalEndsAt);

        Carbon::setTestNow();
    }

    private ?SubscriptionPlan $plan = null;

    private function createPlan(): SubscriptionPlan
    {
        if ($this->plan) {
            return $this->plan;
        }

        return $this->plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'billing_model' => PlanBillingModel::Tiered,
            'interval' => PlanInterval::Monthly,
            'price' => 30,
            'currency' => 'USD',
            'trial_days' => 0,
            'grace_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createLandlord(): User
    {
        $user = User::create([
            'name' => 'Landlord User',
            'email' => 'landlord-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('landlord');

        return $user;
    }

    private function createTenant(): User
    {
        $user = User::create([
            'name' => 'Tenant User',
            'email' => 'tenant-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('tenant');

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createSubscription(array $overrides = []): Subscription
    {
        $plan = $this->createPlan();
        $landlord = $this->createLandlord();

        return Subscription::withoutGlobalScopes()->create(array_merge([
            'landlord_id' => $landlord->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_model' => PlanBillingModel::Tiered,
            'interval' => PlanInterval::Monthly,
            'price' => 30,
            'currency' => 'USD',
            'starts_at' => '2026-06-03',
            'ends_at' => '2026-07-03',
            'grace_ends_at' => '2026-07-10',
            'auto_renew' => true,
        ], $overrides));
    }
}
