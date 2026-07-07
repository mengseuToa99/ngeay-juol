<?php

namespace Tests\Feature;

use App\Enums\BillingType;
use App\Enums\RentalStatus;
use App\Enums\UnitStatus;
use App\Filament\Pages\MonthlyBilling;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Property;
use App\Models\PropertySetting;
use App\Models\PropertyUtility;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityUsage;
use App\Support\ActiveProperty;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyBillingManualAndDesktopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('landlord'));
        Carbon::setTestNow('2026-07-05 09:00:00');
    }

    protected function tearDown(): void
    {
        ActiveProperty::clear();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_from_simple_query_param_renders_back_button(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Prop', 1);

        $this->actingAs($landlord);

        Livewire::test(MonthlyBilling::class, ['from' => 'simple'])
            ->assertSeeHtml('class="rw-sm-back-btn"')
            ->assertSeeHtml('id="rw-sm-back-btn"');
    }

    public function test_manual_billing_mode_toggle_and_validation(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Prop', 2);

        $this->actingAs($landlord);

        // Initially manual mode is false, and 2 rooms are due
        $test = Livewire::test(MonthlyBilling::class)
            ->assertSet('manualMode', false)
            ->assertSet('selectedRentalIds', []);

        // Turn on manual mode
        $test->set('manualMode', true)
            ->call('startBilling'); // with empty selected rentals, should notify/block
        
        $test->assertNotSet('step', 'reading');

        // Select one rental
        $rentals = Rental::where('property_id', $property->id)->get();
        $this->assertCount(2, $rentals);

        $test->set('selectedRentalIds', [$rentals[0]->id])
            ->call('startBilling')
            ->assertSet('step', 'reading');
            
        $this->assertCount(1, $test->instance()->rooms);
    }

    public function test_reactive_rent_recalculation_and_period_validation(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Prop', 1);
        $rental = Rental::where('property_id', $property->id)->firstOrFail();

        $this->actingAs($landlord);

        $test = Livewire::test(MonthlyBilling::class)
            ->set('manualMode', true)
            ->set('selectedRentalIds', [$rental->id])
            ->call('startBilling');

        // Period: 2026-06-01 to 2026-07-05
        $this->assertSame('2026-06-01', $test->instance()->rooms[0]['period_start']);
        $this->assertSame('2026-07-05', $test->instance()->rooms[0]['period_end']);

        // Let's shorten the period to 15 days (prorated rent should change)
        $test->set('rooms.0.period_end', '2026-06-15');
        
        // Assert rent has recalculated reactively
        $expectedProratedRent = round(500 * (15 / 30), 2); // 15 days out of 30 in June
        $this->assertEquals($expectedProratedRent, $test->instance()->rooms[0]['rent']);

        // Set period start after period end -> invalid period
        $test->set('rooms.0.period_start', '2026-06-20')
            ->set('rooms.0.period_end', '2026-06-10')
            ->call('goToReview');

        // Should block and remain on reading step
        $test->assertSet('step', 'reading');
        $this->assertTrue($test->instance()->roomHasInvalidPeriodOrDuplicate(0));
    }

    public function test_duplicate_invoice_detection_and_blocking(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Prop', 1);
        $rental = Rental::where('property_id', $property->id)->firstOrFail();

        // Create an existing invoice for 2026-06-01 -> 2026-07-05
        Invoice::create([
            'rental_id' => $rental->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $rental->tenant_id,
            'invoice_number' => 'INV-TEST-001',
            'amount_due' => 500,
            'period_start' => '2026-06-01',
            'period_end' => '2026-07-05',
            'issue_date' => '2026-07-05',
            'due_date' => '2026-07-12',
            'payment_status' => \App\Enums\InvoiceStatus::Pending,
        ]);

        $this->actingAs($landlord);

        $test = Livewire::test(MonthlyBilling::class)
            ->set('manualMode', true)
            ->set('selectedRentalIds', [$rental->id])
            ->call('startBilling')
            ->set('rooms.0.period_start', '2026-06-01')
            ->set('rooms.0.period_end', '2026-07-05');

        $this->assertTrue($test->instance()->hasDuplicateInvoice(0));

        $test->call('goToReview')
            ->assertSet('step', 'reading'); // Blocks review step
    }

    public function test_next_invoice_date_advancing_rules(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Prop', 2);
        
        $rentals = Rental::where('property_id', $property->id)->orderBy('id')->get();
        $firstRental = $rentals[0];
        $secondRental = $rentals[1];

        // Set expected next invoice date schedule
        $firstRental->update(['next_invoice_date' => '2026-06-01']);
        $secondRental->update(['next_invoice_date' => '2026-07-01']);

        $this->actingAs($landlord);

        // Case 1: Manual Mode but period matches schedule exactly (should advance next_invoice_date)
        Livewire::test(MonthlyBilling::class)
            ->set('manualMode', true)
            ->set('selectedRentalIds', [$firstRental->id])
            ->set('issueDate', '2026-07-05')
            ->call('startBilling')
            ->set('rooms.0.utilities.0.new_reading', '150')
            ->call('goToReview')
            ->call('openCreateConfirmation')
            ->call('createInvoices');

        $this->assertNotNull($firstRental->refresh()->next_invoice_date);
        $this->assertSame('2026-07-01', Carbon::parse($firstRental->next_invoice_date)->toDateString());

        // Case 2: Manual Mode and period does NOT match schedule (should NOT advance next_invoice_date)
        Livewire::test(MonthlyBilling::class)
            ->set('manualMode', true)
            ->set('selectedRentalIds', [$secondRental->id])
            ->set('issueDate', '2026-07-05')
            ->call('startBilling')
            // Change period start to be different from scheduled start
            ->set('rooms.0.period_start', '2026-06-15')
            ->set('rooms.0.utilities.0.new_reading', '150')
            ->call('goToReview')
            ->call('openCreateConfirmation')
            ->call('createInvoices');

        // Next invoice date remains 2026-07-01
        $this->assertSame('2026-07-01', Carbon::parse($secondRental->refresh()->next_invoice_date)->toDateString());
    }

    public function test_invoice_run_not_applicable_override_skips_utility_line_and_keeps_rent_line(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Prop', 1);

        $this->actingAs($landlord);

        Livewire::test(MonthlyBilling::class)
            ->call('chooseProperty', $property->id)
            ->call('startBilling')
            ->set('rooms.0.utilities.0.state_override', 'not_applicable')
            ->set('rooms.0.utilities.0.override_reason', 'No motorbike')
            ->call('goToReview')
            ->call('openCreateConfirmation')
            ->call('createInvoices');

        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, InvoiceLine::count());
        $this->assertSame(0, UtilityUsage::count());
    }

    public function test_desktop_grid_view_entry_and_creation(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Prop', 2);

        $this->actingAs($landlord);

        Livewire::test(MonthlyBilling::class)
            ->assertSet('billingViewMode', 'mobile')
            ->call('startBilling')
            ->set('billingViewMode', 'desktop')
            ->set('rooms.0.utilities.0.new_reading', '150')
            ->set('rooms.1.utilities.0.new_reading', '120')
            ->call('goToReview')
            ->assertSet('step', 'review')
            ->call('openCreateConfirmation')
            ->call('createInvoices');

        $this->assertSame(2, Invoice::count());
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

    private function createPropertyWithDueRooms(User $landlord, string $name, int $roomCount, ?float $previousReading = null): Property
    {
        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => $name,
        ]);

        PropertySetting::create([
            'property_id' => $property->id,
            'currency' => 'USD',
            'invoice_prefix' => 'INV',
            'monthly_billing_enabled' => true,
            'invoice_due_days' => 7,
            'due_day_of_month' => 7,
            'first_month_billing_mode' => \App\Enums\FirstMonthBillingMode::Prorated,
        ]);

        $utility = PropertyUtility::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'name' => 'Electricity',
            'billing_type' => BillingType::Metered,
            'rate' => 0.25,
            'unit_of_measure' => 'kWh',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= $roomCount; $i++) {
            $tenant = User::create([
                'name' => 'Tenant '.$i,
                'email' => 'tenant-'.$name.'-'.$i.'-'.uniqid().'@example.com',
                'password' => bcrypt('password'),
            ]);
            $tenant->assignRole('tenant');

            $unit = Unit::create([
                'property_id' => $property->id,
                'landlord_id' => $landlord->id,
                'room_number' => (string) (100 + $i),
                'room_type' => 'Standard',
                'status' => UnitStatus::Available,
            ]);

            $rental = Rental::create([
                'property_id' => $property->id,
                'landlord_id' => $landlord->id,
                'tenant_id' => $tenant->id,
                'unit_id' => $unit->id,
                'occupant_name' => 'Occupant '.$i,
                'monthly_rent' => 500,
                'status' => RentalStatus::Active,
                'start_date' => '2026-06-01',
                'next_invoice_date' => null,
            ]);

            if ($previousReading !== null) {
                UtilityUsage::create([
                    'property_utility_id' => $utility->id,
                    'unit_id' => $unit->id,
                    'rental_id' => $rental->id,
                    'landlord_id' => $landlord->id,
                    'recorded_by_id' => $landlord->id,
                    'reading_type' => \App\Enums\ReadingType::Actual,
                    'reading_date' => '2026-07-01',
                    'old_reading' => $previousReading - 20,
                    'new_reading' => $previousReading,
                    'amount_used' => 20,
                ]);
            }
        }

        return $property;
    }
}
