<?php

namespace App\Models;

use App\Enums\MoveInChargeType;
use App\Enums\MoveInRequirementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalMoveInRequirement extends Model
{
    protected $fillable = [
        'rental_id', 'move_in_rule_id', 'name', 'charge_type', 'calculation_type',
        'calculation_value', 'calculation_inputs', 'amount', 'currency', 'minimum_required',
        'amount_paid', 'status', 'due_timing', 'blocks_move_in', 'refundable',
        'application_policy', 'override_reason', 'override_by_id', 'overridden_at',
    ];

    protected function casts(): array
    {
        return [
            'charge_type' => MoveInChargeType::class,
            'calculation_value' => 'decimal:4',
            'calculation_inputs' => 'array',
            'amount' => 'decimal:2',
            'minimum_required' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => MoveInRequirementStatus::class,
            'blocks_move_in' => 'boolean',
            'refundable' => 'boolean',
            'overridden_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo { return $this->belongsTo(Rental::class); }
    public function rule(): BelongsTo { return $this->belongsTo(MoveInRule::class, 'move_in_rule_id'); }
    public function overrideBy(): BelongsTo { return $this->belongsTo(User::class, 'override_by_id'); }

    public function outstanding(): float
    {
        return max(0, round((float) $this->minimum_required - (float) $this->amount_paid, 2));
    }
}
