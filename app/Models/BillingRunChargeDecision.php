<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingRunChargeDecision extends Model
{
    protected $fillable = [
        'billing_run_id',
        'rental_id',
        'unit_id',
        'charge_definition_id',
        'property_utility_id',
        'resolved_state',
        'source_scope_type',
        'source_scope_id',
        'reason',
        'amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function chargeDefinition(): BelongsTo
    {
        return $this->belongsTo(ChargeDefinition::class);
    }

    public function propertyUtility(): BelongsTo
    {
        return $this->belongsTo(PropertyUtility::class);
    }
}
