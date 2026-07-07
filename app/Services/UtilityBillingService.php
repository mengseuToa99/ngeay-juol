<?php

namespace App\Services;

use App\Enums\BillingType;
use App\Models\UtilityUsage;
use App\Models\UtilityWaiver;

class UtilityBillingService
{
    /**
     * Compute the charge for a usage reading against its property utility.
     *  - Metered  → amount_used × rate
     *  - Flat     → fixed rate per room
     *  - Shared   → treated as metered until master-meter splitting is built
     * Zeroed when waived (reading-level OR a property/unit/rental waiver).
     *
     * @return array{rate: float, quantity: float, amount: float, is_waived: bool}
     */
    public static function resolveCharge(UtilityUsage $usage, array $manualOverrides = []): array
    {
        $utility = $usage->propertyUtility;
        $currency = $utility ? \App\Support\Money::normalize($utility->currency) : 'USD';
        $decimals = \App\Support\Money::decimals($currency);

        $rate = $utility ? (float) $utility->rate : 0.0;
        $quantity = (float) $usage->amount_used;

        // Resolve state using ChargeRuleResolver
        $resolver = app(\App\Services\ChargeRuleResolver::class);
        $params = array_merge([
            'property_utility_id' => $usage->property_utility_id,
            'rental_id' => $usage->rental_id,
            'unit_id' => $usage->unit_id,
            'date' => $usage->reading_date ? $usage->reading_date->toDateString() : now()->toDateString(),
        ], $manualOverrides);

        $decision = $resolver->resolve($params);

        $waived = $usage->is_waived || $decision['effective_state'] === 'waived';
        $isFree = $decision['effective_state'] === 'free';

        $amount = 0.0;
        if (! $waived && ! $isFree && $decision['should_create_line'] && $utility) {
            $amount = match ($utility->billing_type) {
                BillingType::Flat => round($rate, $decimals),
                default => round($quantity * $rate, $decimals), // Metered (and Shared fallback)
            };
        }

        if ($decision['effective_state'] === 'custom') {
            $amount = (float) $decision['amount'];
            $currency = $decision['currency'];
        }

        return [
            'rate' => $rate,
            'quantity' => $quantity,
            'amount' => $amount,
            'is_waived' => $waived,
            'currency' => $currency,
            'effective_state' => $decision['effective_state'],
            'should_create_line' => $decision['should_create_line'],
            'tenant_facing_label' => $decision['tenant_facing_label'],
            'charge_rule_id' => $decision['source_scope_id'],
            'source_scope_type' => $decision['source_scope_type'],
            'reason' => $decision['reason'],
        ];
    }
}
