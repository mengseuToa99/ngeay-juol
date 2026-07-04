<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProfile extends Model
{
    protected $fillable = [
        'user_id',
        'id_card_number',
        'occupation',
        'workplace',
        'monthly_income',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'guarantor_name',
        'guarantor_phone',
        'guarantor_id_number',
        'guarantor_address',
        'move_in_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'monthly_income' => 'decimal:2',
            'move_in_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
