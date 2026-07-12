<?php

namespace App\Services;

use App\Enums\MoveInRequirementStatus;
use App\Models\Invoice;
use App\Models\MoveInPaymentAllocation;
use Illuminate\Support\Facades\DB;

/** Deterministic FIFO allocation within one currency; never redirects overpayment. */
class MoveInPaymentAllocator
{
    public function allocate(Invoice $invoice, array $attributes): array
    {
        return DB::transaction(function () use ($invoice, $attributes) {
            $payment = $invoice->recordPayment($attributes);
            $remaining = (float) $payment->amount;
            $requirements = $invoice->lines()->with('moveInRequirement')->orderBy('id')->get()->pluck('moveInRequirement')->filter();
            foreach ($requirements as $requirement) {
                if ($remaining <= 0) break;
                $due = $requirement->outstanding();
                $amount = min($remaining, $due);
                if ($amount <= 0) continue;
                $currency = $payment->currency;
                $rate = (float) $payment->exchange_rate;
                MoveInPaymentAllocation::create([
                    'payment_id' => $payment->id, 'rental_move_in_requirement_id' => $requirement->id,
                    'amount' => $amount, 'currency' => $currency,
                    'amount_usd' => $currency === 'USD' ? $amount : round($amount / $rate, 2),
                    'amount_khr' => $currency === 'KHR' ? round($amount) : round($amount * $rate),
                ]);
                $requirement->amount_paid = (float) $requirement->amount_paid + $amount;
                $requirement->status = $requirement->outstanding() <= 0.0001 ? MoveInRequirementStatus::Satisfied : MoveInRequirementStatus::PartiallyPaid;
                $requirement->save();
                $remaining -= $amount;
            }
            return ['payment' => $payment->refresh(), 'unallocated' => round($remaining, 2)];
        });
    }
}
