<?php

namespace Tests\Feature;

use App\Enums\BillingType;
use App\Enums\InvoiceStatus;
use App\Enums\UnitStatus;
use App\Filament\Widgets\OverdueInvoicesWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\RoomStatusWidget;
use App\Filament\Widgets\UtilityUsageWidget;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\PropertyUtility;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityUsage;
use App\Support\ActiveProperty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Database\Seeders\RolesAndPermissionsSeeder;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('landlord'));
    }

    protected function tearDown(): void
    {
        ActiveProperty::clear();
        parent::tearDown();
    }

    public function test_room_status_widget_returns_correct_data(): void
    {
        $landlord = User::create([
            'name' => 'Landlord User',
            'email' => 'landlord@example.com',
            'password' => bcrypt('password'),
        ]);
        $landlord->assignRole('landlord');

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Property Alpha',
        ]);
        ActiveProperty::set($property->id);

        Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 500,
            'status' => UnitStatus::Available,
        ]);

        Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'room_number' => '102',
            'room_type' => 'Standard',
            'rent_amount' => 500,
            'status' => UnitStatus::Occupied,
        ]);

        Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'room_number' => '103',
            'room_type' => 'Standard',
            'rent_amount' => 500,
            'status' => UnitStatus::Occupied,
        ]);

        $this->actingAs($landlord);

        $widget = Livewire::test(RoomStatusWidget::class)->instance();
        $reflector = new \ReflectionMethod($widget, 'getData');
        $reflector->setAccessible(true);
        $data = $reflector->invoke($widget);

        $dataset = $data['datasets'][0]['data'];
        $this->assertEquals([1, 2, 0, 0], $dataset);
    }

    public function test_utility_usage_widget_returns_correct_data_and_scopes_by_year(): void
    {
        $landlord = User::create([
            'name' => 'Landlord User',
            'email' => 'landlord@example.com',
            'password' => bcrypt('password'),
        ]);
        $landlord->assignRole('landlord');

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Property Alpha',
        ]);
        ActiveProperty::set($property->id);

        $utility = PropertyUtility::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'name' => 'Electricity',
            'billing_type' => BillingType::Metered,
            'rate' => 0.15,
            'unit_of_measure' => 'kWh',
        ]);

        $unit = Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 500,
        ]);

        $year = now()->year;
        UtilityUsage::create([
            'unit_id' => $unit->id,
            'property_utility_id' => $utility->id,
            'reading_date' => "{$year}-07-15",
            'old_reading' => 100,
            'new_reading' => 250,
            'amount_used' => 150,
            'recorded_by_id' => $landlord->id,
        ]);

        $this->actingAs($landlord);

        $widget = Livewire::test(UtilityUsageWidget::class)->instance();
        $reflector = new \ReflectionMethod($widget, 'getData');
        $reflector->setAccessible(true);
        $data = $reflector->invoke($widget);

        $datasets = $data['datasets'];
        $this->assertCount(1, $datasets);
        $this->assertEquals(__($utility->name) . ' ($)', $datasets[0]['label']);
        
        $julyData = $datasets[0]['data'][6]; // July is index 6
        $this->assertEquals(22.5, (float) $julyData);
    }

    public function test_revenue_chart_widget_returns_correct_data(): void
    {
        $landlord = User::create([
            'name' => 'Landlord User',
            'email' => 'landlord@example.com',
            'password' => bcrypt('password'),
        ]);
        $landlord->assignRole('landlord');

        $tenant = User::create([
            'name' => 'Tenant User',
            'email' => 'tenant@example.com',
            'password' => bcrypt('password'),
        ]);
        $tenant->assignRole('tenant');

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Property Alpha',
        ]);
        ActiveProperty::set($property->id);

        $unit = Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 500,
        ]);

        $rental = \App\Models\Rental::create([
            'unit_id' => $unit->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'occupant_name' => 'John Doe',
            'start_date' => now()->startOfMonth(),
            'monthly_rent' => 500,
            'status' => \App\Enums\RentalStatus::Active,
        ]);

        $year = now()->year;
        $invoice = Invoice::create([
            'rental_id' => $rental->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-001',
            'amount_due' => 500.0,
            'period_start' => "{$year}-07-01",
            'period_end' => "{$year}-07-31",
            'issue_date' => "{$year}-07-15",
            'due_date' => "{$year}-07-31",
            'payment_status' => InvoiceStatus::Pending,
        ]);

        \App\Models\InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'line_type' => \App\Enums\InvoiceLineType::Rent,
            'description' => 'Rent',
            'unit_price' => 300.0,
            'quantity' => 1,
            'amount' => 300.0,
            'currency' => 'USD',
        ]);

        $invoice->recordPayment([
            'recorded_by_id' => $landlord->id,
            'amount' => 300.0,
            'paid_at' => "{$year}-07-15 12:00:00",
            'method' => \App\Enums\PaymentMethod::Cash,
        ]);

        $this->actingAs($landlord);

        $widget = Livewire::test(RevenueChartWidget::class)->instance();
        $reflector = new \ReflectionMethod($widget, 'getData');
        $reflector->setAccessible(true);
        $data = $reflector->invoke($widget);

        $datasets = $data['datasets'];
        $this->assertCount(4, $datasets);
        
        $currencySymbol = \App\Support\Money::symbol(\App\Support\Money::activeCurrency());
        $this->assertEquals(__('Revenue') . ' (' . $currencySymbol . ')', $datasets[0]['label']);
        $this->assertEquals(300.0, (float) $datasets[0]['data'][6]); // July index 6
    }

    public function test_overdue_invoices_widget_shows_overdue_invoices(): void
    {
        $landlord = User::create([
            'name' => 'Landlord User',
            'email' => 'landlord@example.com',
            'password' => bcrypt('password'),
        ]);
        $landlord->assignRole('landlord');

        $tenant = User::create([
            'name' => 'Tenant User',
            'email' => 'tenant@example.com',
            'password' => bcrypt('password'),
        ]);
        $tenant->assignRole('tenant');

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Property Alpha',
        ]);
        ActiveProperty::set($property->id);

        $unit = Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 500,
        ]);

        $rental = \App\Models\Rental::create([
            'unit_id' => $unit->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'occupant_name' => 'John Doe',
            'start_date' => now()->subMonth(),
            'monthly_rent' => 500,
            'status' => \App\Enums\RentalStatus::Active,
        ]);

        // Overdue invoice (due date in past, unpaid)
        $invoice1 = Invoice::create([
            'rental_id' => $rental->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-001',
            'amount_due' => 500.0,
            'amount_paid' => 0.0,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'issue_date' => now()->subMonth()->startOfMonth(),
            'due_date' => now()->subMonth()->endOfMonth(),
            'payment_status' => InvoiceStatus::Pending,
        ]);

        // Future/current invoice (not overdue yet)
        $invoice2 = Invoice::create([
            'rental_id' => $rental->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-002',
            'amount_due' => 500.0,
            'amount_paid' => 0.0,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issue_date' => now()->startOfMonth(),
            'due_date' => now()->addDays(7),
            'payment_status' => InvoiceStatus::Pending,
        ]);

        $this->actingAs($landlord);

        Livewire::test(OverdueInvoicesWidget::class)
            ->assertCanSeeTableRecords([$invoice1])
            ->assertCanNotSeeTableRecords([$invoice2]);
    }
}
