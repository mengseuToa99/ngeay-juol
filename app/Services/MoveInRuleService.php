<?php

namespace App\Services;

use App\Enums\MoveInCalculationType;
use App\Enums\MoveInChargeType;
use App\Enums\MoveInRequirementStatus;
use App\Models\MoveInRule;
use App\Models\PropertySetting;
use App\Models\Rental;
use App\Models\RentalMoveInRequirement;
use Illuminate\Support\Facades\DB;

class MoveInRuleService
{
    /** Snapshot active property rules onto a rental. Safe to call repeatedly. */
    public function prepare(Rental $rental): Rental
    {
        return DB::transaction(function () use ($rental) {
            if ($rental->moveInRequirements()->exists()) return $rental->load('moveInRequirements');
            $setting = PropertySetting::where('property_id', $rental->property_id)->first();
            $rules = MoveInRule::where('property_id', $rental->property_id)->where('is_active', true)->orderBy('sort_order')->get();
            if ($rules->isEmpty()) {
                $this->materializeLegacyRules($rental, $setting);
                $rules = MoveInRule::where('property_id', $rental->property_id)->where('is_active', true)->orderBy('sort_order')->get();
            }
            foreach ($rules as $rule) {
                $amount = $this->calculate($rule, $rental, $setting);
                $required = $rule->minimum_required !== null ? (float) $rule->minimum_required : $amount;
                RentalMoveInRequirement::create([
                    'rental_id' => $rental->id, 'move_in_rule_id' => $rule->id, 'name' => $rule->name,
                    'charge_type' => $rule->charge_type, 'calculation_type' => $rule->calculation_type,
                    'calculation_value' => $rule->calculation_value, 'calculation_inputs' => [
                        'monthly_rent' => (float) $rental->monthly_rent, 'start_date' => optional($rental->start_date)->toDateString(),
                    ], 'amount' => $amount, 'currency' => $rule->currency ?: $rental->monthly_rent_currency,
                    'minimum_required' => $required, 'status' => $required > 0 ? MoveInRequirementStatus::Outstanding : MoveInRequirementStatus::Satisfied,
                    'due_timing' => $rule->due_timing, 'blocks_move_in' => $rule->blocks_move_in,
                    'refundable' => $rule->refundable, 'application_policy' => $rule->application_policy,
                ]);
            }
            return $rental->load('moveInRequirements');
        });
    }

    /** Convert legacy settings into future-facing structured defaults once. */
    protected function materializeLegacyRules(Rental $rental, ?PropertySetting $setting): void
    {
        $setting ??= new PropertySetting(['first_month_billing_mode' => \App\Enums\FirstMonthBillingMode::FullMonth]);
        $propertyId = $rental->property_id;
        $position = 0;
        $add = function (array $attributes) use ($propertyId, &$position): void {
            MoveInRule::create($attributes + ['property_id' => $propertyId, 'sort_order' => $position++, 'is_active' => true]);
        };
        $blocking = (bool) ($setting->require_first_month_upfront ?? false);
        $add(['name' => 'First-period rent', 'charge_type' => MoveInChargeType::FirstPeriodRent, 'calculation_type' => MoveInCalculationType::FirstPeriodCalculation, 'calculation_value' => null, 'currency' => $rental->monthly_rent_currency, 'blocks_move_in' => $blocking, 'refundable' => false, 'application_policy' => 'first_period']);
        $months = (int) ($setting->upfront_deposit_months ?? 0);
        if ($months > 0) {
            $add(['name' => 'Security deposit', 'charge_type' => MoveInChargeType::SecurityDeposit, 'calculation_type' => MoveInCalculationType::RentMultiplier, 'calculation_value' => $months, 'currency' => $rental->monthly_rent_currency, 'blocks_move_in' => $blocking, 'refundable' => true, 'application_policy' => 'move_out_settlement']);
        }
    }

    public function configurePreset(int $propertyId, string $preset, string $currency = 'USD'): void
    {
        DB::transaction(function () use ($propertyId, $preset, $currency) {
            MoveInRule::where('property_id', $propertyId)->update(['is_active' => false]);
            $rules = [[
                'name' => 'First-period rent', 'charge_type' => MoveInChargeType::FirstPeriodRent,
                'calculation_type' => MoveInCalculationType::FirstPeriodCalculation, 'calculation_value' => null,
                'currency' => $currency, 'blocks_move_in' => $preset !== 'flexible', 'refundable' => false,
                'application_policy' => 'first_period',
            ]];
            if (in_array($preset, ['first_last', 'first_last_deposit'], true)) $rules[] = ['name' => 'Last-month rent credit', 'charge_type' => MoveInChargeType::LastMonthRentCredit, 'calculation_type' => MoveInCalculationType::RentMultiplier, 'calculation_value' => 1, 'currency' => $currency, 'blocks_move_in' => true, 'refundable' => true, 'application_policy' => 'actual_final_period'];
            if (in_array($preset, ['first_deposit', 'first_last_deposit'], true)) $rules[] = ['name' => 'Security deposit', 'charge_type' => MoveInChargeType::SecurityDeposit, 'calculation_type' => MoveInCalculationType::RentMultiplier, 'calculation_value' => 1, 'currency' => $currency, 'blocks_move_in' => true, 'refundable' => true, 'application_policy' => 'move_out_settlement'];
            foreach ($rules as $i => $rule) MoveInRule::updateOrCreate(['property_id' => $propertyId, 'name' => $rule['name']], $rule + ['property_id' => $propertyId, 'sort_order' => $i, 'is_active' => true]);
        });
    }

    public function calculate(MoveInRule $rule, Rental $rental, ?PropertySetting $setting = null): float
    {
        $currency = $rule->currency ?: $rental->monthly_rent_currency;
        $rent = (float) $rental->monthly_rent;
        $value = (float) $rule->calculation_value;
        return match ($rule->calculation_type) {
            MoveInCalculationType::FixedAmount => round($value, \App\Support\Money::decimals($currency)),
            MoveInCalculationType::RentMultiplier => round($rent * max(0, $value), \App\Support\Money::decimals($currency)),
            MoveInCalculationType::PercentageOfRent => round($rent * max(0, $value) / 100, \App\Support\Money::decimals($currency)),
            MoveInCalculationType::FirstPeriodCalculation => ProratingService::compute($setting, $rent, \Carbon\Carbon::parse($rental->start_date), \Carbon\Carbon::parse($rental->start_date)->endOfMonth(), $currency),
            MoveInCalculationType::ManualPerRental => (float) ($rental->security_deposit ?: 0),
        };
    }

    public function readiness(Rental $rental): array
    {
        $requirements = $rental->moveInRequirements()->get();
        if ($rental->move_in_override_reason) {
            $required = $requirements->where('blocks_move_in', true)->sum('minimum_required');
            $paid = $requirements->where('blocks_move_in', true)->sum('amount_paid');
            return ['ready' => true, 'blocking_required' => $required, 'blocking_paid' => $paid, 'blocking_outstanding' => max(0, round($required - $paid, 2)), 'requirements' => $requirements, 'overridden' => true];
        }
        $required = $requirements->where('blocks_move_in', true)->sum('minimum_required');
        $paid = $requirements->where('blocks_move_in', true)->sum('amount_paid');
        $outstanding = max(0, round($required - $paid, 2));
        return ['ready' => $outstanding <= 0.0001, 'blocking_required' => $required, 'blocking_paid' => $paid, 'blocking_outstanding' => $outstanding, 'requirements' => $requirements];
    }

    public function overrideGate(Rental $rental, int $actorId, string $reason, ?string $promisedPaymentDate = null): Rental
    {
        if (trim($reason) === '') throw new \InvalidArgumentException('A move-in override reason is required.');
        return DB::transaction(function () use ($rental, $actorId, $reason, $promisedPaymentDate) {
            $rental->forceFill([
                'move_in_status' => \App\Enums\MoveInReadinessStatus::ReadyForMoveIn,
                'move_in_override_reason' => $reason,
                'move_in_override_at' => now(),
                'move_in_override_by_id' => $actorId,
                'move_in_promised_payment_date' => $promisedPaymentDate,
            ])->saveQuietly();
            return $rental->refresh();
        });
    }
}
