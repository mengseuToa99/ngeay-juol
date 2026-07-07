<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLandlord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeDefinition extends Model
{
    use BelongsToLandlord;
    use SoftDeletes;

    protected $fillable = [
        'property_id',
        'landlord_id',
        'name',
        'category',
        'billing_type',
        'default_amount',
        'default_currency',
        'unit_of_measure',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function resolveLandlordId(): ?int
    {
        return Property::withoutGlobalScopes()->whereKey($this->property_id)->value('landlord_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ChargeRule::class);
    }
}
