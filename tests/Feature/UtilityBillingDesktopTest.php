<?php

namespace Tests\Feature;

use App\Enums\BillingType;
use App\Enums\InvoiceLineType;
use App\Enums\PlanBillingModel;
use App\Enums\PlanInterval;
use App\Enums\ReadingType;
use App\Enums\RentalStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Pages\UtilityBilling;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\PropertySetting;
use App\Models\PropertyUtility;
use App\Models\Rental;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Support\ActiveProperty;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UtilityBillingDesktopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        ActiveProperty::clear();
    }

    /**
     * Helper to set up landlord, property, unit, rental, utilities.
     */
    protected function setupFixture(bool $isReadOnly = false): array
    {
        $landlord = User::factory()->create([
            'email' => 'landlord-' . uniqid() . '@example.com',
        ]);
        $landlord->assignRole('landlord');

        $plan = SubscriptionPlan::firstOrCreate([
            'slug' => 'starter',
        ], [
            'name' => 'Starter',
            'billing_model' => PlanBillingModel::Tiered,
            'interval' => PlanInterval::Monthly,
            'price' => 30,
            'currency' => 'USD',
            'trial_days' => 0,
            'grace_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        if ($isReadOnly) {
            Subscription::withoutGlobalScopes()->create([
                'landlord_id' => $landlord->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'billing_model' => PlanBillingModel::Tiered,
                'interval' => PlanInterval::Monthly,
                'price' => 30,
                'currency' => 'USD',
                'starts_at' => now()->subMonths(2),
                'ends_at' => now()->subDays(30),
                'grace_ends_at' => now()->subDays(20),
                'auto_renew' => true,
            ]);
        } else {
            Subscription::withoutGlobalScopes()->create([
                'landlord_id' => $landlord->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'billing_model' => PlanBillingModel::Tiered,
                'interval' => PlanInterval::Monthly,
                'price' => 30,
                'currency' => 'USD',
                'starts_at' => now()->startOfMonth(),
                'ends_at' => now()->addMonth()->endOfMonth(),
                'auto_renew' => true,
            ]);
        }

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Alpha Property',
        ]);

        PropertySetting::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'monthly_billing_enabled' => true,
        ]);

        $utility = PropertyUtility::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'name' => 'Electricity',
            'unit_of_measure' => 'kWh',
            'billing_type' => BillingType::Metered,
            'rate' => 0.15,
            'is_active' => true,
        ]);

        $unitOne = Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 500,
        ]);

        $unitTwo = Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'room_number' => '102',
            'room_type' => 'Standard',
            'rent_amount' => 600,
        ]);

        $rentalOne = Rental::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => User::factory()->create()->id,
            'unit_id' => $unitOne->id,
            'monthly_rent' => 500,
            'status' => RentalStatus::Active,
            'start_date' => now()->subMonths(1)->startOfMonth()->toDateString(),
            'next_invoice_date' => now()->startOfMonth()->toDateString(),
        ]);

        $rentalTwo = Rental::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => User::factory()->create()->id,
            'unit_id' => $unitTwo->id,
            'monthly_rent' => 600,
            'status' => RentalStatus::Active,
            'start_date' => now()->subMonths(1)->startOfMonth()->toDateString(),
            'next_invoice_date' => now()->startOfMonth()->toDateString(),
        ]);

        return compact('landlord', 'property', 'utility', 'unitOne', 'unitTwo', 'rentalOne', 'rentalTwo');
    }

    public function test_utility_billing_loads_desktop_workspace_for_active_property(): void
    {
        $fixture = $this->setupFixture();
        ActiveProperty::set($fixture['property']->id);

        Livewire::actingAs($fixture['landlord'])
            ->test(UtilityBilling::class)
            ->assertSet('propertyId', $fixture['property']->id)
            ->assertSeeHtml('id="utility-billing-grid"')
            ->assertSeeHtml('id="utility-billing-toolbar"')
            ->assertSeeHtml('id="utility-create-invoices"');
    }

    public function test_landlord_can_enter_multiple_room_readings_without_room_by_room_navigation(): void
    {
        $fixture = $this->setupFixture();
        ActiveProperty::set($fixture['property']->id);

        $test = Livewire::actingAs($fixture['landlord'])
            ->test(UtilityBilling::class)
            ->set('rooms.0.utilities.0.new_reading', '100')
            ->set('rooms.1.utilities.0.new_reading', '200');

        $rooms = $test->get('rooms');
        $this->assertEquals('100', $rooms[0]['utilities'][0]['new_reading']);
        $this->assertEquals('200', $rooms[1]['utilities'][0]['new_reading']);

        // Assert no invoices or utility usage recorded yet
        $this->assertEquals(0, Invoice::count());
        $this->assertEquals(0, \App\Models\UtilityUsage::count());
    }

    public function test_missing_readings_block_invoice_creation(): void
    {
        $fixture = $this->setupFixture();
        ActiveProperty::set($fixture['property']->id);

        // Leave room 1 new_reading blank (missing)
        Livewire::actingAs($fixture['landlord'])
            ->test(UtilityBilling::class)
            ->set('rooms.0.utilities.0.new_reading', '100')
            ->set('rooms.1.utilities.0.new_reading', '') // missing
            ->call('createInvoices')
            ->assertSet('step', 'reading'); // Blocks and remains in reading state

        $this->assertEquals(0, Invoice::count());
    }

    public function test_lower_reading_requires_override_reason(): void
    {
        $fixture = $this->setupFixture();
        ActiveProperty::set($fixture['property']->id);

        // Previous reading is 0 (old_reading default is 0).
        // Let's set old_reading to 100 first to test lower reading.
        // We will mock the latest utility usage to make old_reading = 100.
        \App\Models\UtilityUsage::create([
            'property_utility_id' => $fixture['utility']->id,
            'unit_id' => $fixture['unitOne']->id,
            'rental_id' => $fixture['rentalOne']->id,
            'landlord_id' => $fixture['landlord']->id,
            'recorded_by_id' => $fixture['landlord']->id,
            'reading_type' => ReadingType::Actual,
            'reading_date' => now()->subDays(5)->toDateString(),
            'old_reading' => 0,
            'new_reading' => 100,
            'amount_used' => 100,
        ]);

        $test = Livewire::actingAs($fixture['landlord'])
            ->test(UtilityBilling::class)
            // old_reading is now 100. We enter 90 (lower).
            ->set('rooms.0.utilities.0.new_reading', '90')
            ->set('rooms.1.utilities.0.new_reading', '50') // room 2 is valid
            ->call('createInvoices')
            ->assertSet('step', 'reading'); // Blocked!

        $this->assertEquals(0, Invoice::count());

        // Now provide override reason
        $test->set('rooms.0.utilities.0.override_reason', 'Meter replaced')
            ->call('createInvoices')
            ->assertSet('step', 'result'); // Success!

        $this->assertEquals(2, Invoice::count());
    }

    public function test_utility_only_invoice_creation_from_desktop_grid(): void
    {
        $fixture = $this->setupFixture();
        ActiveProperty::set($fixture['property']->id);

        Livewire::actingAs($fixture['landlord'])
            ->test(UtilityBilling::class)
            ->set('rooms.0.utilities.0.new_reading', '100')
            ->set('rooms.1.utilities.0.new_reading', '200')
            ->call('createInvoices')
            ->assertSet('step', 'result');

        $this->assertEquals(2, Invoice::count());

        foreach (Invoice::all() as $invoice) {
            $this->assertGreaterThan(0, $invoice->lines()->count());
            // Assert no Rent line is present
            $this->assertDatabaseMissing('invoice_lines', [
                'invoice_id' => $invoice->id,
                'line_type' => InvoiceLineType::Rent->value,
            ]);
            // Assert Utility line is present
            $this->assertDatabaseHas('invoice_lines', [
                'invoice_id' => $invoice->id,
                'line_type' => InvoiceLineType::Utility->value,
            ]);
        }
    }

    public function test_skipped_rows_do_not_create_invoices(): void
    {
        $fixture = $this->setupFixture();
        ActiveProperty::set($fixture['property']->id);

        Livewire::actingAs($fixture['landlord'])
            ->test(UtilityBilling::class)
            ->set('rooms.0.utilities.0.new_reading', '100')
            ->set('rooms.1.utilities.0.new_reading', '200')
            ->call('toggleRoomSkip', 0) // Skip first room
            ->call('createInvoices')
            ->assertSet('step', 'result');

        // Only 1 invoice should be created (for the second room)
        $this->assertEquals(1, Invoice::count());
        $invoice = Invoice::first();
        $this->assertEquals($fixture['rentalTwo']->id, $invoice->rental_id);
    }

    public function test_duplicate_invoice_period_is_blocked_or_skipped_safely(): void
    {
        $fixture = $this->setupFixture();
        ActiveProperty::set($fixture['property']->id);

        // Pre-create an invoice for the first rental for the same period
        Invoice::create([
            'rental_id' => $fixture['rentalOne']->id,
            'property_id' => $fixture['property']->id,
            'landlord_id' => $fixture['landlord']->id,
            'tenant_id' => $fixture['rentalOne']->tenant_id,
            'invoice_number' => 'INV-DUP',
            'amount_due' => 15,
            'period_start' => now()->subMonths(1)->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        Livewire::actingAs($fixture['landlord'])
            ->test(UtilityBilling::class)
            ->set('rooms.0.utilities.0.new_reading', '100')
            ->set('rooms.1.utilities.0.new_reading', '200')
            ->call('createInvoices')
            ->assertSet('step', 'result');

        // Only 1 new invoice should be created (for the second room, since first is duplicate)
        // Total invoices should be 2 (1 pre-created + 1 new)
        $this->assertEquals(2, Invoice::count());
    }

    public function test_read_only_subscription_blocks_create_action(): void
    {
        $fixture = $this->setupFixture(isReadOnly: true);
        ActiveProperty::set($fixture['property']->id);

        Livewire::actingAs($fixture['landlord'])
            ->test(UtilityBilling::class)
            ->set('rooms.0.utilities.0.new_reading', '100')
            ->set('rooms.1.utilities.0.new_reading', '200')
            ->call('createInvoices')
            ->assertSet('step', 'reading'); // Blocks and remains in reading state

        $this->assertEquals(0, Invoice::count());
    }
}
