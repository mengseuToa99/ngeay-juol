<?php

namespace App\Models;

use App\Enums\InvoiceLineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id',
        'line_type',
        'utility_usage_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'currency',
        'unit_price_currency',
        'amount_usd',
        'amount_khr',
        'unit_price_usd',
        'unit_price_khr',
        'exchange_rate',
        'is_waived',
        'charge_state',
        'charge_state_label',
        'charge_state_reason',
        'charge_definition_id',
        'charge_rule_id',
    ];

    protected function casts(): array
    {
        return [
            'line_type' => InvoiceLineType::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
            'amount_usd' => 'decimal:2',
            'amount_khr' => 'decimal:0',
            'unit_price_usd' => 'decimal:4',
            'unit_price_khr' => 'decimal:4',
            'exchange_rate' => 'decimal:4',
            'is_waived' => 'boolean',
        ];
    }

    /** Keep the parent invoice's amount_due in sync with its line items. */
    protected static function booted(): void
    {
        static::saved(fn (InvoiceLine $line) => $line->invoice?->recalculateAmountDue());
        static::deleted(fn (InvoiceLine $line) => $line->invoice?->recalculateAmountDue());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function utilityUsage(): BelongsTo
    {
        return $this->belongsTo(UtilityUsage::class);
    }

    public function resolvedChargeState(): string
    {
        return $this->charge_state ?: ($this->is_waived ? 'waived' : 'normal');
    }

    public function resolvedChargeStateLabel(): string
    {
        if ($this->charge_state_label) {
            return __($this->charge_state_label);
        }

        return match ($this->resolvedChargeState()) {
            'free' => __('Free'),
            'waived' => __('Waived'),
            'not_applicable' => __('Not applicable'),
            'skipped_this_cycle' => __('Skipped this cycle'),
            'custom' => __('Adjusted'),
            default => __('Normal'),
        };
    }

    public function resolvedChargeStateReason(): ?string
    {
        return $this->charge_state_reason ?: null;
    }

    public function shouldAppearOnTenantInvoice(): bool
    {
        return ! in_array($this->resolvedChargeState(), ['not_applicable', 'skipped_this_cycle'], true);
    }

    public function isConcessionState(): bool
    {
        return in_array($this->resolvedChargeState(), ['free', 'waived'], true);
    }

    public function sourceScopeLabel(): string
    {
        if ($this->charge_rule_id) {
            return __('Rule # :id', ['id' => $this->charge_rule_id]);
        }

        if ($this->charge_definition_id) {
            return __('Charge # :id', ['id' => $this->charge_definition_id]);
        }

        if ($this->resolvedChargeState() === 'waived' && ! $this->charge_state) {
            return __('Legacy waiver');
        }

        return __('Invoice snapshot');
    }

    /** Get dynamically translated description for invoices. */
    public function getTranslatedDescription(): string
    {
        $desc = $this->description;
        if (str_ends_with($desc, ' usage')) {
            $utilityName = substr($desc, 0, -6);
            $translatedUtility = __($utilityName);
            return app()->getLocale() === 'km'
                ? 'ការប្រើប្រាស់' . $translatedUtility
                : $translatedUtility . ' usage';
        }

        return __($desc);
    }
}
