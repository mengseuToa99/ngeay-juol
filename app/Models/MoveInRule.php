<?php

namespace App\Models;

use App\Enums\MoveInCalculationType;
use App\Enums\MoveInChargeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MoveInRule extends Model
{
    protected $fillable = [
        'property_id', 'name', 'charge_type', 'calculation_type', 'calculation_value',
        'currency', 'due_timing', 'blocks_move_in', 'minimum_required', 'refundable',
        'application_policy', 'allow_rental_override', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'charge_type' => MoveInChargeType::class,
            'calculation_type' => MoveInCalculationType::class,
            'calculation_value' => 'decimal:4',
            'blocks_move_in' => 'boolean',
            'minimum_required' => 'decimal:2',
            'refundable' => 'boolean',
            'allow_rental_override' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function requirements(): HasMany { return $this->hasMany(RentalMoveInRequirement::class); }
}
