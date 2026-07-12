<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveInPaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'rental_move_in_requirement_id', 'amount', 'currency', 'amount_usd', 'amount_khr'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'amount_usd' => 'decimal:2', 'amount_khr' => 'decimal:0']; }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function requirement(): BelongsTo { return $this->belongsTo(RentalMoveInRequirement::class, 'rental_move_in_requirement_id'); }
}
