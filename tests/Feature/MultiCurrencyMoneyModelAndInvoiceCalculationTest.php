<?php

namespace Tests\Feature;

use App\Enums\BillingType;
use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\PropertySetting;
use App\Models\PropertyUtility;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityUsage;
use App\Services\ExchangeRateService;
use App\Services\InvoiceBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCurrencyMoneyModelAndInvoiceCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;
    protected User $tenant;
    protected Property $property;
    protected PropertySetting $settings;
    protected Unit $unit;
    protected Rental $rental;
    protected InvoiceBuilderService $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = app(InvoiceBuilderService::class);

        $this->landlord = User::create([
            'name' => 'Landlord',
            'email' => 'landlord@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->tenant = User::create([
            'name' => 'Tenant',
            'email' => 'tenant@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->property = Property::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Rose Villa',
        ]);

        $this->settings = PropertySetting::create([
            'property_id' => $this->property->id,
            'currency' => 'USD',
            'usd_khr_exchange_rate' => 4000.0,
            'exchange_rate_source' => 'manual',
            'exchange_rate_date' => now()->toDateString(),
        ]);

        $this->unit = Unit::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 100.00,
            'rent_currency' => 'USD',
        ]);

        $this->rental = Rental::create([
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $this->tenant->id,
            'unit_id' => $this->unit->id,
            'monthly_rent' => 100.00,
            'monthly_rent_currency' => 'USD',
            'security_deposit' => 100.00,
            'security_deposit_currency' => 'USD',
            'start_date' => now()->toDateString(),
            'status' => \App\Enums\RentalStatus::Active,
        ]);
    }

    public function test_creates_usd_only_invoice_and_totals_remain_correct(): void
    {
        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4000.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        $invoice = $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => true,
        ]);

        $this->assertEquals(100.00, $invoice->amount_due);
        $this->assertEquals(100.00, $invoice->total_usd);
        $this->assertEquals(400000, $invoice->total_khr);
        $this->assertEquals(100.00, $invoice->native_usd_total);
        $this->assertEquals(0, $invoice->native_khr_total);
    }

    public function test_creates_khr_only_invoice_and_totals_remain_correct(): void
    {
        $this->rental->update([
            'monthly_rent' => 400000,
            'monthly_rent_currency' => 'KHR',
        ]);

        $this->settings->update([
            'currency' => 'KHR',
            'usd_khr_exchange_rate' => 4000.0,
        ]);

        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4000.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        $invoice = $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => true,
        ]);

        $this->assertEquals(400000, $invoice->amount_due);
        $this->assertEquals(100.00, $invoice->total_usd);
        $this->assertEquals(400000, $invoice->total_khr);
        $this->assertEquals(0, $invoice->native_usd_total);
        $this->assertEquals(400000, $invoice->native_khr_total);
    }

    public function test_creates_mixed_usd_khr_invoice_with_snapshot_rate(): void
    {
        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4100.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        $this->settings->update([
            'exchange_rate_source' => 'NBC',
        ]);

        $utility = PropertyUtility::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'name' => 'Electricity',
            'billing_type' => BillingType::Flat,
            'rate' => 20500, // 20,500 KHR
            'currency' => 'KHR',
        ]);

        $usage = UtilityUsage::create([
            'unit_id' => $this->unit->id,
            'property_utility_id' => $utility->id,
            'rental_id' => $this->rental->id,
            'recorded_by_id' => $this->landlord->id,
            'reading_date' => now()->toDateString(),
            'new_reading' => 1,
            'amount_used' => 1,
        ]);

        $invoice = $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => true,
            'usages' => [$usage],
        ]);

        // Rent is 100 USD (original)
        // Utility is 20,500 KHR (original)
        // NBC rate is 4,100 KHR per USD
        // 20,500 KHR / 4,100 = 5 USD
        // Total USD = 100 USD + 5 USD = 105 USD
        // Total KHR = 410,000 KHR (rent converted) + 20,500 KHR = 430,500 KHR
        
        $this->assertEquals(4100.0, $invoice->usd_khr_rate);
        $this->assertEquals('NBC', $invoice->exchange_rate_source);
        $this->assertEquals(105.00, $invoice->total_usd);
        $this->assertEquals(430500, $invoice->total_khr);
        $this->assertEquals(100.00, $invoice->native_usd_total);
        $this->assertEquals(20500, $invoice->native_khr_total);
    }

    public function test_invoice_lines_preserve_original_currencies(): void
    {
        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4100.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        $this->settings->update(['exchange_rate_source' => 'NBC']);

        $utility = PropertyUtility::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'name' => 'Water',
            'billing_type' => BillingType::Flat,
            'rate' => 8200, // 8,200 KHR
            'currency' => 'KHR',
        ]);

        $usage = UtilityUsage::create([
            'unit_id' => $this->unit->id,
            'property_utility_id' => $utility->id,
            'rental_id' => $this->rental->id,
            'recorded_by_id' => $this->landlord->id,
            'reading_date' => now()->toDateString(),
            'new_reading' => 1,
            'amount_used' => 1,
        ]);

        $invoice = $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => true,
            'usages' => [$usage],
        ]);

        $rentLine = $invoice->lines()->where('line_type', InvoiceLineType::Rent)->first();
        $this->assertEquals('USD', $rentLine->currency);
        $this->assertEquals(100.00, $rentLine->amount);
        $this->assertEquals(100.00, $rentLine->amount_usd);
        $this->assertEquals(410000, $rentLine->amount_khr);

        $utilityLine = $invoice->lines()->where('line_type', InvoiceLineType::Utility)->first();
        $this->assertEquals('KHR', $utilityLine->currency);
        $this->assertEquals(8200, $utilityLine->amount);
        $this->assertEquals(2.00, $utilityLine->amount_usd);
        $this->assertEquals(8200, $utilityLine->amount_khr);
    }

    public function test_changing_utility_currency_after_invoice_generation_does_not_change_old_invoice_line_currency(): void
    {
        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4000.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        $utility = PropertyUtility::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'name' => 'Electricity',
            'billing_type' => BillingType::Flat,
            'rate' => 20000, // 20,000 KHR
            'currency' => 'KHR',
        ]);

        $usage = UtilityUsage::create([
            'unit_id' => $this->unit->id,
            'property_utility_id' => $utility->id,
            'rental_id' => $this->rental->id,
            'recorded_by_id' => $this->landlord->id,
            'reading_date' => now()->toDateString(),
            'new_reading' => 1,
            'amount_used' => 1,
        ]);

        $invoice = $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => false,
            'usages' => [$usage],
        ]);

        $line = $invoice->lines()->first();
        $this->assertEquals('KHR', $line->currency);

        // Update utility currency to USD
        $utility->update(['currency' => 'USD', 'rate' => 5.00]);

        // Refresh line, it should still be KHR
        $line->refresh();
        $this->assertEquals('KHR', $line->currency);
        $this->assertEquals(20000, $line->amount);
    }

    public function test_changing_exchange_rate_after_invoice_generation_does_not_change_old_invoice_totals(): void
    {
        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4000.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        $invoice = $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => true,
        ]);

        $this->assertEquals(4000.0, $invoice->usd_khr_rate);
        $this->assertEquals(100.00, $invoice->total_usd);
        $this->assertEquals(400000, $invoice->total_khr);

        $this->settings->update(['usd_khr_exchange_rate' => 4500.0]);

        $invoice->refresh();
        $this->assertEquals(4000.0, $invoice->usd_khr_rate);
        $this->assertEquals(100.00, $invoice->total_usd);
        $this->assertEquals(400000, $invoice->total_khr);
    }

    public function test_payment_in_usd_updates_paid_and_balance_totals(): void
    {
        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4000.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        $invoice = $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => true,
        ]);

        $invoice->recordPayment([
            'amount' => 40.00,
            'currency' => 'USD',
            'recorded_by_id' => $this->landlord->id,
        ]);

        $invoice->refresh();
        $this->assertEquals(40.00, $invoice->paid_usd);
        $this->assertEquals(160000, $invoice->paid_khr);
        $this->assertEquals(60.00, $invoice->balance_usd);
        $this->assertEquals(240000, $invoice->balance_khr);
        $this->assertEquals(InvoiceStatus::Partial, $invoice->payment_status);
    }

    public function test_payment_in_khr_updates_paid_and_balance_totals(): void
    {
        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4000.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        $invoice = $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => true,
        ]);

        $invoice->recordPayment([
            'amount' => 200000, // 200,000 KHR (50 USD)
            'currency' => 'KHR',
            'recorded_by_id' => $this->landlord->id,
        ]);

        $invoice->refresh();
        $this->assertEquals(50.00, $invoice->paid_usd);
        $this->assertEquals(200000, $invoice->paid_khr);
        $this->assertEquals(50.00, $invoice->balance_usd);
        $this->assertEquals(200000, $invoice->balance_khr);
        $this->assertEquals(InvoiceStatus::Partial, $invoice->payment_status);
    }

    public function test_partial_mixed_currency_payment_resolves_partial_status_correctly(): void
    {
        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4000.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        $invoice = $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => true,
        ]);

        $invoice->recordPayment([
            'amount' => 20.00,
            'currency' => 'USD',
            'recorded_by_id' => $this->landlord->id,
        ]);

        $invoice->recordPayment([
            'amount' => 120000, // 120,000 KHR = 30 USD
            'currency' => 'KHR',
            'recorded_by_id' => $this->landlord->id,
        ]);

        $invoice->refresh();
        $this->assertEquals(50.00, $invoice->paid_usd);
        $this->assertEquals(200000, $invoice->paid_khr);
        $this->assertEquals(InvoiceStatus::Partial, $invoice->payment_status);
    }

    public function test_full_payment_in_either_currency_resolves_paid_status_correctly(): void
    {
        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4000.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        $invoice = $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => true,
        ]);

        $invoice->recordPayment([
            'amount' => 400000, // 400,000 KHR = 100 USD
            'currency' => 'KHR',
            'recorded_by_id' => $this->landlord->id,
        ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->payment_status);
    }

    public function test_nbc_fetch_failure_blocks_mixed_invoice_when_no_fallback_or_manual_rate_exists(): void
    {
        // 1. Set exchange_rate_source to NBC
        $this->settings->update([
            'exchange_rate_source' => 'NBC',
            'usd_khr_exchange_rate' => null,
        ]);

        // 2. Mock NBC fetch failure
        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andThrow(new \RuntimeException('NBC Service Down'));
        });

        // 3. Create a KHR utility usage so the invoice becomes mixed-currency (Rent = USD, Utility = KHR)
        $utility = PropertyUtility::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'name' => 'Electricity',
            'billing_type' => BillingType::Flat,
            'rate' => 20000,
            'currency' => 'KHR',
        ]);

        $usage = UtilityUsage::create([
            'unit_id' => $this->unit->id,
            'property_utility_id' => $utility->id,
            'rental_id' => $this->rental->id,
            'recorded_by_id' => $this->landlord->id,
            'reading_date' => now()->toDateString(),
            'new_reading' => 1,
            'amount_used' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot build mixed-currency invoice because no exchange rate is available');

        $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => true,
            'usages' => [$usage],
        ]);
    }

    public function test_manual_rate_overrides_nbc_rate(): void
    {
        $this->mock(ExchangeRateService::class, function ($mock) {
            // NBC returns 4,100
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4100.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        // Property has a manual rate of 4,050
        $this->settings->update([
            'exchange_rate_source' => 'manual',
            'usd_khr_exchange_rate' => 4050.0,
        ]);

        $invoice = $this->builder->create([
            'rental' => $this->rental,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => true,
        ]);

        $this->assertEquals(4050.0, $invoice->usd_khr_rate);
        $this->assertEquals('manual', $invoice->exchange_rate_source);
        $this->assertEquals(100.00, $invoice->total_usd);
        $this->assertEquals(405000, $invoice->total_khr);
    }
}
