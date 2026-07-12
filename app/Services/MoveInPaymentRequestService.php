<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MoveInPaymentRequestService
{
    /** Build the one classified request for a prepared tenancy, once. */
    public function issue(Rental $rental): Invoice
    {
        return DB::transaction(function () use ($rental) {
            $rental->loadMissing('moveInRequirements');
            $existing = $rental->invoices()->where('notes', 'like', '%move-in payment request%')->first();
            if ($existing) return $existing;
            $requirements = $rental->moveInRequirements->sortBy('id')->values();
            $first = $requirements->firstWhere('charge_type', \App\Enums\MoveInChargeType::FirstPeriodRent);
            $adhoc = $requirements->filter(fn ($r) => $r->id !== $first?->id)->map(fn ($r) => [
                'description' => $r->name,
                'amount' => $r->amount,
                'currency' => $r->currency,
                'billing_classification' => $r->charge_type->value,
            ])->values()->all();
            $start = Carbon::parse($rental->start_date);
            $invoice = app(InvoiceBuilderService::class)->create([
                'rental' => $rental, 'period_start' => $start,
                'period_end' => $start->copy()->endOfMonth(), 'issue_date' => now(),
                'due_date' => $start, 'include_rent' => $first !== null,
                'is_first_invoice' => $first !== null, 'adhoc' => $adhoc, 'usages' => [],
                'notes' => 'Move-in payment request',
            ]);
            foreach ($invoice->lines as $line) {
                $classification = $line->billing_classification;
                if ($classification === 'first_period_rent') {
                    $requirement = $first;
                } else {
                    $requirement = $requirements->first(fn ($r) => $r->charge_type->value === $classification && ! $invoice->lines->where('rental_move_in_requirement_id', $r->id)->count());
                }
                if ($requirement) $line->update(['rental_move_in_requirement_id' => $requirement->id]);
            }
            return $invoice->refresh();
        });
    }
}
