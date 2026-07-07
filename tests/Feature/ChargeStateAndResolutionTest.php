<?php

namespace Tests\Feature;

use App\Enums\BillingType;
use App\Enums\InvoiceLineType;
use App\Models\BillingRunChargeDecision;
use App\Models\ChargeDefinition;
use App\Models\ChargeRule;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\PropertySetting;
use App\Models\PropertyUtility;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityUsage;
use App\Models\UtilityWaiver;
use App\Services\ExchangeRateService;
use App\Services\InvoiceBuilderService;
use App\Services\ChargeRuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeStateAndResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;
    protected User $tenant;
    protected Property $property;
    protected PropertySetting $settings;
    protected Unit $unit1;
    protected Unit $unit2;
    protected Rental $rental1;
    protected Rental $rental2;
    protected PropertyUtility $utility;
    protected ChargeDefinition $definition;
    protected InvoiceBuilderService $builder;
    protected ChargeRuleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = app(InvoiceBuilderService::class);
        $this->resolver = app(ChargeRuleResolver::class);

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

        $this->unit1 = Unit::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 100.00,
            'rent_currency' => 'USD',
        ]);

        $this->unit2 = Unit::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'room_number' => '102',
            'room_type' => 'Standard',
            'rent_amount' => 120.00,
            'rent_currency' => 'USD',
        ]);

        $this->rental1 = Rental::create([
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $this->tenant->id,
            'unit_id' => $this->unit1->id,
            'monthly_rent' => 100.00,
            'monthly_rent_currency' => 'USD',
            'security_deposit' => 100.00,
            'security_deposit_currency' => 'USD',
            'start_date' => now()->toDateString(),
            'status' => \App\Enums\RentalStatus::Active,
        ]);

        $this->rental2 = Rental::create([
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $this->tenant->id,
            'unit_id' => $this->unit2->id,
            'monthly_rent' => 120.00,
            'monthly_rent_currency' => 'USD',
            'security_deposit' => 120.00,
            'security_deposit_currency' => 'USD',
            'start_date' => now()->toDateString(),
            'status' => \App\Enums\RentalStatus::Active,
        ]);

        $this->definition = ChargeDefinition::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'name' => 'Wifi Fee',
            'category' => 'internet',
            'billing_type' => 'flat',
            'default_amount' => 10.00,
            'default_currency' => 'USD',
        ]);

        $this->utility = PropertyUtility::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'charge_definition_id' => $this->definition->id,
            'name' => 'Wifi Fee',
            'billing_type' => BillingType::Flat,
            'rate' => 10.00,
            'currency' => 'USD',
        ]);
    }

    public function test_property_wide_normal_charge_applies_to_all_rooms(): void
    {
        // 1. Create a property-wide rule (state = normal)
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'property',
            'scope_id' => $this->property->id,
            'state' => 'normal',
        ]);

        // 2. Resolve for rental 1 and rental 2
        $decision1 = $this->resolver->resolve([
            'charge_definition_id' => $this->definition->id,
            'rental_id' => $this->rental1->id,
        ]);
        $decision2 = $this->resolver->resolve([
            'charge_definition_id' => $this->definition->id,
            'rental_id' => $this->rental2->id,
        ]);

        $this->assertEquals('normal', $decision1['effective_state']);
        $this->assertEquals(10.00, $decision1['amount']);
        $this->assertEquals('USD', $decision1['currency']);
        $this->assertTrue($decision1['should_create_line']);

        $this->assertEquals('normal', $decision2['effective_state']);
        $this->assertEquals(10.00, $decision2['amount']);
        $this->assertEquals('USD', $decision2['currency']);
        $this->assertTrue($decision2['should_create_line']);
    }

    public function test_unit_level_not_applicable_overrides_property_normal(): void
    {
        // 1. Property rule: normal
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'property',
            'scope_id' => $this->property->id,
            'state' => 'normal',
        ]);

        // 2. Unit 1 override: not_applicable
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'unit',
            'scope_id' => $this->unit1->id,
            'state' => 'not_applicable',
        ]);

        // 3. Resolve for rental 1 (unit 1) -> not_applicable
        $decision1 = $this->resolver->resolve([
            'charge_definition_id' => $this->definition->id,
            'rental_id' => $this->rental1->id,
        ]);

        // 4. Resolve for rental 2 (unit 2) -> normal
        $decision2 = $this->resolver->resolve([
            'charge_definition_id' => $this->definition->id,
            'rental_id' => $this->rental2->id,
        ]);

        $this->assertEquals('not_applicable', $decision1['effective_state']);
        $this->assertFalse($decision1['should_create_line']);

        $this->assertEquals('normal', $decision2['effective_state']);
        $this->assertTrue($decision2['should_create_line']);
    }

    public function test_rental_level_free_overrides_unit_property_normal(): void
    {
        // 1. Property rule: normal
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'property',
            'scope_id' => $this->property->id,
            'state' => 'normal',
        ]);

        // 2. Unit 1 rule: normal
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'unit',
            'scope_id' => $this->unit1->id,
            'state' => 'normal',
        ]);

        // 3. Rental 1 override: free
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'rental',
            'scope_id' => $this->rental1->id,
            'state' => 'free',
        ]);

        // 4. Resolve for rental 1 -> free
        $decision = $this->resolver->resolve([
            'charge_definition_id' => $this->definition->id,
            'rental_id' => $this->rental1->id,
        ]);

        $this->assertEquals('free', $decision['effective_state']);
        $this->assertEquals(0.0, $decision['amount']);
        $this->assertTrue($decision['should_create_line']);
    }

    public function test_invoice_run_waived_overrides_all_persistent_rules(): void
    {
        // Rental 1 persistent rule: normal
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'rental',
            'scope_id' => $this->rental1->id,
            'state' => 'normal',
        ]);

        // Resolve with manual override waived
        $decision = $this->resolver->resolve([
            'charge_definition_id' => $this->definition->id,
            'rental_id' => $this->rental1->id,
            'manual_state' => 'waived',
            'manual_reason' => 'Special promotion',
        ]);

        $this->assertEquals('waived', $decision['effective_state']);
        $this->assertEquals(0.0, $decision['amount']);
        $this->assertEquals('Special promotion', $decision['reason']);
        $this->assertTrue($decision['should_create_line']);
    }

    public function test_free_charge_creates_zero_invoice_line_with_free_label(): void
    {
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'rental',
            'scope_id' => $this->rental1->id,
            'state' => 'free',
        ]);

        $usage = UtilityUsage::create([
            'unit_id' => $this->unit1->id,
            'property_utility_id' => $this->utility->id,
            'rental_id' => $this->rental1->id,
            'recorded_by_id' => $this->landlord->id,
            'reading_date' => now()->toDateString(),
            'new_reading' => 1,
            'amount_used' => 1,
        ]);

        $invoice = $this->builder->create([
            'rental' => $this->rental1,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => false,
            'usages' => [$usage],
        ]);

        $line = $invoice->lines()->first();
        $this->assertEquals(0.0, $line->amount);
        $this->assertStringContainsString('Free', $line->description);
        $this->assertEquals('free', $line->charge_state);
        $this->assertEquals('Free', $line->charge_state_label);
    }

    public function test_waived_charge_creates_zero_invoice_line_with_waived_label_and_reason(): void
    {
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'rental',
            'scope_id' => $this->rental1->id,
            'state' => 'waived',
            'reason' => 'Late setup',
        ]);

        $usage = UtilityUsage::create([
            'unit_id' => $this->unit1->id,
            'property_utility_id' => $this->utility->id,
            'rental_id' => $this->rental1->id,
            'recorded_by_id' => $this->landlord->id,
            'reading_date' => now()->toDateString(),
            'new_reading' => 1,
            'amount_used' => 1,
        ]);

        $invoice = $this->builder->create([
            'rental' => $this->rental1,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => false,
            'usages' => [$usage],
        ]);

        $line = $invoice->lines()->first();
        $this->assertEquals(0.0, $line->amount);
        $this->assertStringContainsString('Waived', $line->description);
        $this->assertStringContainsString('Late setup', $line->description);
        $this->assertEquals('waived', $line->charge_state);
        $this->assertEquals('Late setup', $line->charge_state_reason);
    }

    public function test_not_applicable_charge_creates_no_tenant_invoice_line(): void
    {
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'rental',
            'scope_id' => $this->rental1->id,
            'state' => 'not_applicable',
        ]);

        $usage = UtilityUsage::create([
            'unit_id' => $this->unit1->id,
            'property_utility_id' => $this->utility->id,
            'rental_id' => $this->rental1->id,
            'recorded_by_id' => $this->landlord->id,
            'reading_date' => now()->toDateString(),
            'new_reading' => 1,
            'amount_used' => 1,
        ]);

        $invoice = $this->builder->create([
            'rental' => $this->rental1,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => false,
            'usages' => [$usage],
        ]);

        // Lines count should be 0 because Wifi Fee is not applicable
        $this->assertCount(0, $invoice->lines);

        // Should create an audit record in billing_run_charge_decisions
        $this->assertDatabaseHas('billing_run_charge_decisions', [
            'rental_id' => $this->rental1->id,
            'property_utility_id' => $this->utility->id,
            'resolved_state' => 'not_applicable',
        ]);
    }

    public function test_skipped_this_cycle_creates_no_tenant_invoice_line_and_is_visible_in_audit(): void
    {
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'rental',
            'scope_id' => $this->rental1->id,
            'state' => 'skipped_this_cycle',
            'reason' => 'Holiday skip',
        ]);

        $usage = UtilityUsage::create([
            'unit_id' => $this->unit1->id,
            'property_utility_id' => $this->utility->id,
            'rental_id' => $this->rental1->id,
            'recorded_by_id' => $this->landlord->id,
            'reading_date' => now()->toDateString(),
            'new_reading' => 1,
            'amount_used' => 1,
        ]);

        $invoice = $this->builder->create([
            'rental' => $this->rental1,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => false,
            'usages' => [$usage],
            'billing_run_id' => 'run_123',
        ]);

        $this->assertCount(0, $invoice->lines);

        // Audit decision entry exists
        $this->assertDatabaseHas('billing_run_charge_decisions', [
            'billing_run_id' => 'run_123',
            'rental_id' => $this->rental1->id,
            'resolved_state' => 'skipped_this_cycle',
            'reason' => 'Holiday skip',
        ]);
    }

    public function test_custom_charge_uses_override_amount_and_currency(): void
    {
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'rental',
            'scope_id' => $this->rental1->id,
            'state' => 'custom',
            'amount_override' => 20500, // 20,500 KHR override
            'currency_override' => 'KHR',
        ]);

        $this->mock(ExchangeRateService::class, function ($mock) {
            $mock->shouldReceive('fetchUsdToKhr')->andReturn([
                'rate' => 4100.0,
                'date' => now()->toDateString(),
                'source' => 'NBC',
            ]);
        });

        $this->settings->update(['exchange_rate_source' => 'NBC']);

        $usage = UtilityUsage::create([
            'unit_id' => $this->unit1->id,
            'property_utility_id' => $this->utility->id,
            'rental_id' => $this->rental1->id,
            'recorded_by_id' => $this->landlord->id,
            'reading_date' => now()->toDateString(),
            'new_reading' => 1,
            'amount_used' => 1,
        ]);

        $invoice = $this->builder->create([
            'rental' => $this->rental1,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => false,
            'usages' => [$usage],
        ]);

        $line = $invoice->lines()->first();
        // Uses KHR and override amount
        $this->assertEquals('KHR', $line->currency);
        $this->assertEquals(20500, $line->amount);
        
        // Converted values using snapshot exchange rate 4100.0
        // 20,500 KHR / 4100.0 = 5 USD
        $this->assertEquals(5.00, $line->amount_usd);
        $this->assertEquals(20500, $line->amount_khr);
    }

    public function test_rule_effective_dates_are_respected(): void
    {
        // Rule that is effective only in the future
        ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'rental',
            'scope_id' => $this->rental1->id,
            'state' => 'free',
            'effective_from' => now()->addMonth()->toDateString(),
            'effective_until' => now()->addMonths(2)->toDateString(),
        ]);

        // Resolving today -> should be normal (default)
        $decisionToday = $this->resolver->resolve([
            'charge_definition_id' => $this->definition->id,
            'rental_id' => $this->rental1->id,
            'date' => now()->toDateString(),
        ]);

        // Resolving next month -> should be free
        $decisionFuture = $this->resolver->resolve([
            'charge_definition_id' => $this->definition->id,
            'rental_id' => $this->rental1->id,
            'date' => now()->addMonth()->toDateString(),
        ]);

        $this->assertEquals('normal', $decisionToday['effective_state']);
        $this->assertEquals('free', $decisionFuture['effective_state']);
    }

    public function test_old_utility_waiver_data_still_resolves_as_waived(): void
    {
        // Create an old legacy utility waiver
        UtilityWaiver::create([
            'property_utility_id' => $this->utility->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'rental_id' => $this->rental1->id,
            'waived' => true,
            'created_by_id' => $this->landlord->id,
        ]);

        $decision = $this->resolver->resolve([
            'property_utility_id' => $this->utility->id,
            'rental_id' => $this->rental1->id,
        ]);

        $this->assertEquals('waived', $decision['effective_state']);
        $this->assertEquals(0.0, $decision['amount']);
        $this->assertEquals('waiver', $decision['source_scope_type']);
    }

    public function test_changing_rule_after_invoice_generation_does_not_change_old_invoice_lines(): void
    {
        // 1. Create a persistent free rule
        $rule = ChargeRule::create([
            'charge_definition_id' => $this->definition->id,
            'landlord_id' => $this->landlord->id,
            'property_id' => $this->property->id,
            'scope_type' => 'rental',
            'scope_id' => $this->rental1->id,
            'state' => 'free',
        ]);

        $usage = UtilityUsage::create([
            'unit_id' => $this->unit1->id,
            'property_utility_id' => $this->utility->id,
            'rental_id' => $this->rental1->id,
            'recorded_by_id' => $this->landlord->id,
            'reading_date' => now()->toDateString(),
            'new_reading' => 1,
            'amount_used' => 1,
        ]);

        $invoice = $this->builder->create([
            'rental' => $this->rental1,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'include_rent' => false,
            'usages' => [$usage],
        ]);

        $line = $invoice->lines()->first();
        $this->assertEquals('free', $line->charge_state);
        $this->assertEquals(0.0, $line->amount);

        // 2. Change rule to normal / custom
        $rule->update([
            'state' => 'custom',
            'amount_override' => 50.00,
            'currency_override' => 'USD',
        ]);

        // 3. Refresh old invoice line, it should remain free/0.0
        $line->refresh();
        $this->assertEquals('free', $line->charge_state);
        $this->assertEquals(0.0, $line->amount);
    }
}
