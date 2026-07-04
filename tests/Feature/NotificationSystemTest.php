<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\PlanBillingModel;
use App\Enums\PlanInterval;
use App\Enums\RentalStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\MaintenanceMessage;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Rental;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\InvoiceGeneratedNotification;
use App\Notifications\MaintenanceMessagePostedNotification;
use App\Notifications\MaintenanceRequestCreatedNotification;
use App\Notifications\MaintenanceStatusChangedNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\PaymentRecordedNotification;
use App\Notifications\SubscriptionExpiringSoonNotification;
use App\Notifications\SubscriptionPastDueNotification;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;

    protected User $tenant;

    protected Property $property;

    protected Unit $unit;

    protected Rental $rental;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->landlord = User::factory()->create(['email' => 'landlord@example.com']);
        $this->landlord->assignRole('landlord');

        $this->tenant = User::factory()->create(['email' => 'tenant@example.com']);
        $this->tenant->assignRole('tenant');

        $this->property = Property::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Property Alpha',
        ]);

        $this->unit = Unit::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 500,
        ]);

        $this->rental = Rental::create([
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'unit_id' => $this->unit->id,
            'monthly_rent' => 500,
            'security_deposit' => 0,
            'status' => RentalStatus::Active,
            'start_date' => '2026-07-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_creating_invoice_sends_database_notification_to_tenant(): void
    {
        $invoice = $this->createInvoice();

        $this->assertDatabaseHas('notifications', [
            'type' => InvoiceGeneratedNotification::class,
            'notifiable_type' => $this->tenant->getMorphClass(),
            'notifiable_id' => $this->tenant->id,
        ]);

        $this->assertEquals($invoice->id, $this->tenant->notifications()->first()->data['invoice_id']);
    }

    public function test_recording_payment_sends_database_notification_to_tenant(): void
    {
        $invoice = $this->createInvoice();

        $payment = $invoice->recordPayment([
            'recorded_by_id' => $this->landlord->id,
            'amount' => 200,
            'paid_at' => '2026-07-03 10:00:00',
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => PaymentRecordedNotification::class,
            'notifiable_type' => $this->tenant->getMorphClass(),
            'notifiable_id' => $this->tenant->id,
        ]);

        $paymentNotification = $this->tenant->notifications()
            ->where('type', PaymentRecordedNotification::class)
            ->first();

        $this->assertEquals($payment->id, $paymentNotification->data['payment_id']);
    }

    public function test_overdue_invoice_command_notifies_tenant_once_per_day(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $invoice = $this->createInvoice([
            'due_date' => '2026-07-07',
            'payment_status' => InvoiceStatus::Pending,
        ]);

        Artisan::call('invoices:notify-overdue');
        Artisan::call('invoices:notify-overdue');

        $this->assertDatabaseHas('notifications', [
            'type' => InvoiceOverdueNotification::class,
            'notifiable_type' => $this->tenant->getMorphClass(),
            'notifiable_id' => $this->tenant->id,
        ]);

        $this->assertEquals(1, $this->tenant->notifications()
            ->where('type', InvoiceOverdueNotification::class)
            ->count());
        $this->assertEquals($invoice->id, $this->tenant->notifications()
            ->where('type', InvoiceOverdueNotification::class)
            ->first()
            ->data['invoice_id']);
    }

    public function test_maintenance_request_and_status_updates_notify_right_users(): void
    {
        $manager = User::factory()->create([
            'email' => 'manager@example.com',
            'manages_landlord_id' => $this->landlord->id,
        ]);
        $manager->assignRole('landlord_manager');

        $request = $this->createMaintenanceRequest();

        foreach ([$this->landlord, $manager] as $user) {
            $this->assertDatabaseHas('notifications', [
                'type' => MaintenanceRequestCreatedNotification::class,
                'notifiable_type' => $user->getMorphClass(),
                'notifiable_id' => $user->id,
            ]);
        }

        $request->update(['status' => MaintenanceStatus::InProgress]);

        $this->assertDatabaseHas('notifications', [
            'type' => MaintenanceStatusChangedNotification::class,
            'notifiable_type' => $this->tenant->getMorphClass(),
            'notifiable_id' => $this->tenant->id,
        ]);
    }

    public function test_posting_maintenance_message_notifies_other_participant(): void
    {
        $request = $this->createMaintenanceRequest();

        $message = MaintenanceMessage::create([
            'request_id' => $request->id,
            'sender_id' => $this->tenant->id,
            'body' => 'The sink is still leaking.',
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => MaintenanceMessagePostedNotification::class,
            'notifiable_type' => $this->landlord->getMorphClass(),
            'notifiable_id' => $this->landlord->id,
        ]);

        $this->assertEquals($message->id, $this->landlord->notifications()
            ->where('type', MaintenanceMessagePostedNotification::class)
            ->first()
            ->data['maintenance_message_id']);
    }

    public function test_subscription_dunning_notifications_are_not_duplicated_for_same_day(): void
    {
        Carbon::setTestNow('2026-07-03 09:00:00');

        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'billing_model' => PlanBillingModel::Flat,
            'interval' => PlanInterval::Monthly,
            'price' => 19,
            'currency' => 'USD',
            'grace_days' => 7,
        ]);

        $subscription = Subscription::create([
            'landlord_id' => $this->landlord->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_model' => PlanBillingModel::Flat,
            'interval' => PlanInterval::Monthly,
            'price' => 19,
            'currency' => 'USD',
            'starts_at' => '2026-06-03',
            'ends_at' => '2026-07-06',
            'grace_ends_at' => '2026-07-13',
            'auto_renew' => true,
        ]);

        $pastDueLandlord = User::factory()->create(['email' => 'past-due-landlord@example.com']);
        $pastDueLandlord->assignRole('landlord');

        $pastDueSubscription = Subscription::create([
            'landlord_id' => $pastDueLandlord->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_model' => PlanBillingModel::Flat,
            'interval' => PlanInterval::Monthly,
            'price' => 19,
            'currency' => 'USD',
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-07-01',
            'grace_ends_at' => '2026-07-08',
            'auto_renew' => true,
        ]);

        Artisan::call('subscriptions:process', ['--dunning' => true]);
        Artisan::call('subscriptions:process', ['--dunning' => true]);

        $this->assertDatabaseHas('notifications', [
            'type' => SubscriptionExpiringSoonNotification::class,
            'notifiable_type' => $this->landlord->getMorphClass(),
            'notifiable_id' => $this->landlord->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => SubscriptionPastDueNotification::class,
            'notifiable_type' => $pastDueLandlord->getMorphClass(),
            'notifiable_id' => $pastDueLandlord->id,
        ]);

        $this->assertEquals($subscription->id, $this->landlord->notifications()->first()->data['subscription_id']);
        $this->assertEquals(1, $this->landlord->notifications()
            ->where('type', SubscriptionExpiringSoonNotification::class)
            ->count());
        $this->assertEquals($pastDueSubscription->id, $pastDueLandlord->notifications()
            ->where('type', SubscriptionPastDueNotification::class)
            ->first()
            ->data['subscription_id']);
        $this->assertEquals(1, $pastDueLandlord->notifications()
            ->where('type', SubscriptionPastDueNotification::class)
            ->count());
    }

    private function createInvoice(array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'rental_id' => $this->rental->id,
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-001',
            'amount_due' => 500,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-07',
            'payment_status' => InvoiceStatus::Pending,
        ], $overrides));
    }

    private function createMaintenanceRequest(): MaintenanceRequest
    {
        return MaintenanceRequest::create([
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'rental_id' => $this->rental->id,
            'title' => 'Leaking sink',
            'description' => 'Water is leaking under the sink.',
            'status' => MaintenanceStatus::Open,
        ]);
    }
}
