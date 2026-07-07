<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLandlord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeRule extends Model
{
    use BelongsToLandlord;
    use SoftDeletes;

    protected $fillable = [
        'charge_definition_id',
        'property_utility_id',
        'landlord_id',
        'property_id',
        'scope_type',
        'scope_id',
        'state',
        'amount_override',
        'currency_override',
        'effective_from',
        'effective_until',
        'reason',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_override' => 'decimal:4',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function resolveLandlordId(): ?int
    {
        return Property::withoutGlobalScopes()->whereKey($this->property_id)->value('landlord_id');
    }

    public function chargeDefinition(): BelongsTo
    {
        return $this->belongsTo(ChargeDefinition::class);
    }

    public function propertyUtility(): BelongsTo
    {
        return $this->belongsTo(PropertyUtility::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
