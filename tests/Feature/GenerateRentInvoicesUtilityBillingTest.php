<?php

namespace Tests\Feature;

use App\Enums\BillingType;
use App\Enums\InvoiceLineType;
use App\Enums\ReadingType;
use App\Enums\RentalStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Property;
use App\Models\PropertyUtility;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityUsage;
use App\Models\UtilityWaiver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GenerateRentInvoicesUtilityBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_generate_rent_invoices_can_leave_utilities_unbilled(): void
    {
        ['usage' => $usage] = $this->createBillingFixture();

        $this->artisan('invoices:generate-rent', [
            '--date' => '2026-07-01',
            '--without-utilities' => true,
        ])->assertSuccessful();

        $invoice = Invoice::query()->firstOrFail();

        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $invoice->id,
            'line_type' => InvoiceLineType::Rent->value,
            'amount' => '500.00',
        ]);

        $this->assertDatabaseMissing('invoice_lines', [
            'utility_usage_id' => $usage->id,
        ]);
    }

    public function test_generate_rent_invoices_includes_unbilled_utility_usage_for_the_period(): void
    {
        ['usage' => $usage] = $this->createBillingFixture();

        $this->artisan('invoices:generate-rent', [
            '--date' => '2026-07-01',
        ])->assertSuccessful();

        $invoice = Invoice::query()->firstOrFail();

        $this->assertSame(2, $invoice->lines()->count());
        $this->assertEquals(515.00, (float) $invoice->fresh()->amount_due);
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $invoice->id,
            'line_type' => InvoiceLineType::Utility->value,
            'utility_usage_id' => $usage->id,
            'quantity' => '100.000',
            'unit_price' => '0.1500',
            'amount' => '15.00',
            'is_waived' => false,
        ]);
    }

    public function test_generate_rent_invoices_is_idempotent_and_skips_already_billed_usage(): void
    {
        ['rental' => $rental, 'usage' => $usage] = $this->createBillingFixture();

        $alreadyBilledUsage = $this->createUsage($rental, '2026-07-12', 25);
        $existingInvoice = Invoice::create([
            'rental_id' => $rental->id,
            'property_id' => $rental->property_id,
            'landlord_id' => $rental->landlord_id,
            'tenant_id' => $rental->tenant_id,
            'invoice_number' => 'INV-EXISTING',
            'amount_due' => 0,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-08',
        ]);
        $existingInvoice->lines()->create([
            'line_type' => InvoiceLineType::Utility,
            'utility_usage_id' => $alreadyBilledUsage->id,
            'description' => 'Already billed usage',
            'quantity' => 25,
            'unit_price' => 0.15,
            'amount' => 3.75,
        ]);

        $this->artisan('invoices:generate-rent', [
            '--date' => '2026-07-01',
        ])->assertSuccessful();

        $this->artisan('invoices:generate-rent', [
            '--date' => '2026-07-01',
        ])->assertSuccessful();

        $this->assertSame(2, Invoice::query()->count());
        $this->assertSame(1, InvoiceLine::query()->where('utility_usage_id', $usage->id)->count());
        $this->assertSame(1, InvoiceLine::query()->where('utility_usage_id', $alreadyBilledUsage->id)->count());
    }

    public function test_generate_rent_invoices_respects_utility_waivers(): void
    {
        ['landlord' => $landlord, 'rental' => $rental, 'utility' => $utility, 'usage' => $usage] = $this->createBillingFixture();

        UtilityWaiver::create([
            'property_utility_id' => $utility->id,
            'landlord_id' => $landlord->id,
            'rental_id' => $rental->id,
            'waived' => true,
            'created_by_id' => $landlord->id,
        ]);

        $this->artisan('invoices:generate-rent', [
            '--date' => '2026-07-01',
        ])->assertSuccessful();

        $invoice = Invoice::query()->firstOrFail();

        $this->assertEquals(500.00, (float) $invoice->fresh()->amount_due);
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $invoice->id,
            'line_type' => InvoiceLineType::Utility->value,
            'utility_usage_id' => $usage->id,
            'amount' => '0.00',
            'is_waived' => true,
        ]);
    }

    /**
     * @return array{landlord: User, tenant: User, property: Property, unit: Unit, rental: Rental, utility: PropertyUtility, usage: UtilityUsage}
     */
    protected function createBillingFixture(): array
    {
        $landlord = User::factory()->create();
        $tenant = User::factory()->create();

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Property Alpha',
        ]);

        $unit = Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 500,
        ]);

        $rental = Rental::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'monthly_rent' => 500,
            'status' => RentalStatus::Active,
            'start_date' => '2026-07-01',
        ]);

        $utility = PropertyUtility::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'name' => 'Electricity',
            'unit_of_measure' => 'kWh',
            'billing_type' => BillingType::Metered,
            'rate' => 0.15,
        ]);

        $usage = $this->createUsage($rental, '2026-07-15', 100, $utility);

        return compact('landlord', 'tenant', 'property', 'unit', 'rental', 'utility', 'usage');
    }

    protected function createUsage(
        Rental $rental,
        string $readingDate,
        float $amountUsed,
        ?PropertyUtility $utility = null,
    ): UtilityUsage {
        $utility ??= PropertyUtility::query()->where('property_id', $rental->property_id)->firstOrFail();

        return UtilityUsage::create([
            'property_utility_id' => $utility->id,
            'unit_id' => $rental->unit_id,
            'rental_id' => $rental->id,
            'landlord_id' => $rental->landlord_id,
            'recorded_by_id' => $rental->landlord_id,
            'reading_type' => ReadingType::Actual,
            'reading_date' => $readingDate,
            'old_reading' => 0,
            'new_reading' => $amountUsed,
            'amount_used' => $amountUsed,
        ]);
    }
}
