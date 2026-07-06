<?php

namespace Tests\Feature;

use App\Enums\BillingType;
use App\Enums\RentalStatus;
use App\Enums\UnitStatus;
use App\Filament\Pages\MonthlyBilling;
use App\Models\Invoice;
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

class PhoneFirstMonthlyBillingTest extends TestCase
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

    public function test_one_property_landlord_skips_property_picker_and_starts_in_flow(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Property Alpha', 1);

        $this->actingAs($landlord);

        Livewire::test(MonthlyBilling::class)
            ->assertSet('step', 'start')
            ->assertSet('propertyId', $property->id);
    }

    public function test_multi_property_landlord_without_active_property_sees_sidebar_blocked_state(): void
    {
        $landlord = $this->createLandlord();
        $propertyA = $this->createPropertyWithDueRooms($landlord, 'Alpha', 2);
        $propertyB = $this->createPropertyWithDueRooms($landlord, 'Beta', 1);

        ActiveProperty::clear();
        $this->actingAs($landlord);

        Livewire::test(MonthlyBilling::class)
            ->assertSet('step', 'blocked')
            ->assertSet('propertyId', null)
            ->assertSee(__('Select a property from the sidebar to start billing.'))
            ->assertSee(__('All properties'))
            ->assertDontSee(__('Select a property first'));
    }

    public function test_selecting_property_loads_only_that_property_rooms(): void
    {
        $landlord = $this->createLandlord();
        $propertyA = $this->createPropertyWithDueRooms($landlord, 'Alpha', 2);
        $propertyB = $this->createPropertyWithDueRooms($landlord, 'Beta', 1);

        ActiveProperty::clear();
        $this->actingAs($landlord);

        $test = Livewire::test(MonthlyBilling::class)
            ->call('chooseProperty', $propertyA->id)
            ->call('startBilling');

        $rooms = $test->instance()->rooms;

        $this->assertCount(2, $rooms);
        $this->assertSame(['101', '102'], array_column($rooms, 'room_number'));
        $this->assertFalse(in_array('201', array_column($rooms, 'room_number'), true));
        $this->assertSame($propertyA->id, $test->instance()->propertyId);
        $this->assertNotSame($propertyB->id, $test->instance()->propertyId);
    }

    public function test_readings_live_in_component_state_until_final_invoice_creation(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Alpha', 1);

        $this->actingAs($landlord);

        $test = Livewire::test(MonthlyBilling::class)
            ->call('chooseProperty', $property->id)
            ->call('startBilling')
            ->set('rooms.0.utilities.0.new_reading', '150');

        $this->assertSame('150', $test->instance()->rooms[0]['utilities'][0]['new_reading']);
        $this->assertDatabaseMissing('utility_usages', [
            'rental_id' => $test->instance()->rooms[0]['rental_id'],
        ]);

        $test
            ->call('goToReview')
            ->call('openCreateConfirmation')
            ->call('createInvoices');

        $this->assertDatabaseCount('utility_usages', 1);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_review_blocks_creation_when_readings_are_missing_and_room_is_not_skipped(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Alpha', 1);

        $this->actingAs($landlord);

        $test = Livewire::test(MonthlyBilling::class)
            ->call('chooseProperty', $property->id)
            ->call('startBilling')
            ->call('goToReview');

        $this->assertSame('reading', $test->instance()->step);
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, UtilityUsage::count());
    }

    public function test_skipped_rooms_do_not_create_invoices(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Alpha', 2);

        $this->actingAs($landlord);

        $test = Livewire::test(MonthlyBilling::class)
            ->call('chooseProperty', $property->id)
            ->call('startBilling')
            ->set('rooms.0.utilities.0.new_reading', '150')
            ->call('toggleRoomSkip', 1)
            ->call('goToReview')
            ->call('openCreateConfirmation')
            ->call('createInvoices');

        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, UtilityUsage::count());
        $this->assertSame(0, Rental::withoutGlobalScopes()->findOrFail($test->instance()->rooms[1]['rental_id'])->invoices()->count());
    }

    public function test_lower_reading_blocks_review_until_override_reason_is_provided(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Alpha', 1, previousReading: 100);

        $this->actingAs($landlord);

        $test = Livewire::test(MonthlyBilling::class)
            ->call('chooseProperty', $property->id)
            ->call('startBilling')
            ->set('rooms.0.utilities.0.new_reading', '90')
            ->call('goToReview');

        $this->assertSame('reading', $test->instance()->step);

        $test
            ->set('rooms.0.utilities.0.override_reason', 'Meter reset')
            ->call('goToReview');

        $this->assertSame('review', $test->instance()->step);
    }

    public function test_rerunning_final_creation_does_not_duplicate_invoices_or_utility_lines(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Alpha', 1);

        $this->actingAs($landlord);

        $test = Livewire::test(MonthlyBilling::class)
            ->call('chooseProperty', $property->id)
            ->call('startBilling')
            ->set('rooms.0.utilities.0.new_reading', '150')
            ->call('goToReview')
            ->call('openCreateConfirmation')
            ->call('createInvoices');

        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, UtilityUsage::count());
        $this->assertSame(2, \App\Models\InvoiceLine::count());

        $test
            ->call('openCreateConfirmation')
            ->call('createInvoices');

        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, UtilityUsage::count());
        $this->assertSame(2, \App\Models\InvoiceLine::count());
    }

    public function test_next_invoice_date_only_advances_for_successfully_invoiced_rooms(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPropertyWithDueRooms($landlord, 'Alpha', 2);

        $this->actingAs($landlord);

        $firstRental = Rental::withoutGlobalScopes()->where('property_id', $property->id)->orderBy('id')->firstOrFail();
        $secondRental = Rental::withoutGlobalScopes()->where('property_id', $property->id)->orderByDesc('id')->firstOrFail();

        Livewire::test(MonthlyBilling::class)
            ->call('chooseProperty', $property->id)
            ->call('startBilling')
            ->set('rooms.0.utilities.0.new_reading', '150')
            ->call('toggleRoomSkip', 1)
            ->call('goToReview')
            ->call('openCreateConfirmation')
            ->call('createInvoices');

        $this->assertNotNull($firstRental->refresh()->next_invoice_date);
        $this->assertNull($secondRental->refresh()->next_invoice_date);
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
