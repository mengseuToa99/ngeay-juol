<?php

namespace Tests\Feature;

use App\Enums\PlanBillingModel;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Widgets\AdminPlatformStatsWidget;
use App\Models\Property;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Database\Seeders\RolesAndPermissionsSeeder;

class AdminDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    public function test_admin_panel_registers_platform_stats_widget(): void
    {
        $widgets = \Filament\Facades\Filament::getPanel('admin')->getWidgets();

        $this->assertContains(AdminPlatformStatsWidget::class, $widgets);
    }

    public function test_admin_platform_stats_widget_uses_platform_wide_counts_and_monthly_revenue(): void
    {
        $staff = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $staff->assignRole('super_admin');

        $landlordOne = User::create([
            'name' => 'Landlord One',
            'email' => 'landlord1@example.com',
            'password' => bcrypt('password'),
        ]);
        $landlordOne->assignRole('landlord');

        $landlordTwo = User::create([
            'name' => 'Landlord Two',
            'email' => 'landlord2@example.com',
            'password' => bcrypt('password'),
        ]);
        $landlordTwo->assignRole('landlord');

        $planOne = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'billing_model' => PlanBillingModel::Tiered,
            'interval' => PlanInterval::Monthly,
            'price' => 25,
            'currency' => 'USD',
            'trial_days' => 0,
            'grace_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $planTwo = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'billing_model' => PlanBillingModel::Tiered,
            'interval' => PlanInterval::Monthly,
            'price' => 50,
            'currency' => 'USD',
            'trial_days' => 0,
            'grace_days' => 0,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Property::create([
            'landlord_id' => $landlordOne->id,
            'name' => 'Alpha Property',
        ]);

        Property::create([
            'landlord_id' => $landlordTwo->id,
            'name' => 'Beta Property',
        ]);

        $activeSubscription = Subscription::withoutGlobalScopes()->create([
            'landlord_id' => $landlordOne->id,
            'plan_id' => $planOne->id,
            'status' => SubscriptionStatus::Active,
            'billing_model' => PlanBillingModel::Tiered,
            'interval' => PlanInterval::Monthly,
            'price' => 25,
            'currency' => 'USD',
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
        ]);

        Subscription::withoutGlobalScopes()->create([
            'landlord_id' => $landlordTwo->id,
            'plan_id' => $planTwo->id,
            'status' => SubscriptionStatus::Suspended,
            'billing_model' => PlanBillingModel::Tiered,
            'interval' => PlanInterval::Monthly,
            'price' => 50,
            'currency' => 'USD',
            'starts_at' => now()->subMonth()->startOfMonth(),
            'ends_at' => now()->subMonth()->endOfMonth(),
            'auto_renew' => false,
        ]);

        SubscriptionPayment::withoutGlobalScopes()->create([
            'subscription_id' => $activeSubscription->id,
            'landlord_id' => $landlordOne->id,
            'plan_id' => $planOne->id,
            'amount' => 25,
            'currency' => 'USD',
            'status' => SubscriptionPaymentStatus::Succeeded,
            'paid_at' => now()->startOfMonth()->addDays(3),
            'covers_from' => now()->startOfMonth(),
            'covers_to' => now()->endOfMonth(),
            'gateway' => 'manual',
            'receipt_number' => 'RCPT-001',
        ]);

        SubscriptionPayment::withoutGlobalScopes()->create([
            'subscription_id' => $activeSubscription->id,
            'landlord_id' => $landlordOne->id,
            'plan_id' => $planOne->id,
            'amount' => 40,
            'currency' => 'USD',
            'status' => SubscriptionPaymentStatus::Succeeded,
            'paid_at' => now()->subMonth()->startOfMonth()->addDays(3),
            'covers_from' => now()->subMonth()->startOfMonth(),
            'covers_to' => now()->subMonth()->endOfMonth(),
            'gateway' => 'manual',
            'receipt_number' => 'RCPT-002',
        ]);

        SubscriptionPayment::withoutGlobalScopes()->create([
            'subscription_id' => $activeSubscription->id,
            'landlord_id' => $landlordOne->id,
            'plan_id' => $planOne->id,
            'amount' => 15,
            'currency' => 'USD',
            'status' => SubscriptionPaymentStatus::Pending,
            'paid_at' => null,
            'covers_from' => now()->startOfMonth(),
            'covers_to' => now()->endOfMonth(),
            'gateway' => 'manual',
            'receipt_number' => 'RCPT-003',
        ]);

        SubscriptionPayment::withoutGlobalScopes()->create([
            'subscription_id' => $activeSubscription->id,
            'landlord_id' => $landlordOne->id,
            'plan_id' => $planOne->id,
            'amount' => 18,
            'currency' => 'USD',
            'status' => SubscriptionPaymentStatus::Failed,
            'paid_at' => now()->startOfMonth()->addDays(5),
            'covers_from' => now()->startOfMonth(),
            'covers_to' => now()->endOfMonth(),
            'gateway' => 'manual',
            'receipt_number' => 'RCPT-004',
        ]);

        $this->actingAs($staff);

        $widget = Livewire::test(AdminPlatformStatsWidget::class)->instance();
        $reflector = new \ReflectionMethod($widget, 'getStats');
        $reflector->setAccessible(true);
        $stats = $reflector->invoke($widget);

        $this->assertCount(4, $stats);
        $this->assertSame(__('Landlords'), $stats[0]->getLabel());
        $this->assertSame(2, $stats[0]->getValue());
        $this->assertSame(__('Active subscriptions'), $stats[1]->getLabel());
        $this->assertSame(1, $stats[1]->getValue());
        $this->assertSame(__('Pending subscription payments'), $stats[2]->getLabel());
        $this->assertSame(1, $stats[2]->getValue());
        $this->assertSame(__('Monthly subscription revenue'), $stats[3]->getLabel());
        $this->assertSame('$25.00', $stats[3]->getValue());
    }
}
