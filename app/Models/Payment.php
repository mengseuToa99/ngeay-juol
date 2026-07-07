<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Notifications\PaymentRecordedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'recorded_by_id',
        'amount',
        'currency',
        'amount_usd',
        'amount_khr',
        'exchange_rate',
        'exchange_rate_source',
        'paid_at',
        'method',
        'transaction_ref',
        'receipt_number',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_usd' => 'decimal:2',
            'amount_khr' => 'decimal:0',
            'exchange_rate' => 'decimal:4',
            'paid_at' => 'datetime',
            'method' => PaymentMethod::class,
        ];
    }

    /**
     * Keep the invoice ledger consistent no matter how a payment is written
     * (recordPayment, Filament, API, console). This is the single choke point
     * that guarantees amount_paid + payment_status never drift.
     */
    protected static function booted(): void
    {
        static::saving(function (Payment $payment) {
            $payment->currency = \App\Support\Money::normalize($payment->currency ?: ($payment->invoice?->property?->settings?->currency ?? 'USD'));
            
            // conversion rate is the invoice's saved rate
            $rate = (float) ($payment->exchange_rate ?: ($payment->invoice?->usd_khr_rate ?: ($payment->invoice?->property?->settings?->usd_khr_exchange_rate ?: 4000)));
            $payment->exchange_rate = $rate;
            if (empty($payment->exchange_rate_source)) {
                $payment->exchange_rate_source = $payment->invoice?->exchange_rate_source ?: 'invoice_snapshot';
            }
            
            $amount = (float) $payment->amount;
            if ($payment->currency === 'USD') {
                $payment->amount_usd = $amount;
                $payment->amount_khr = round($amount * $rate, 0);
            } else { // KHR
                $payment->amount_khr = round($amount, 0);
                $payment->amount_usd = $rate > 0 ? round($amount / $rate, 2) : 0.0;
            }
        });

        static::created(function (Payment $payment) {
            $payment->loadMissing('invoice.tenant');
            $payment->invoice?->tenant?->notify(new PaymentRecordedNotification($payment));
        });

        static::saved(fn (Payment $payment) => $payment->invoice?->recalculateFromLedger());
        static::deleted(fn (Payment $payment) => $payment->invoice?->recalculateFromLedger());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
