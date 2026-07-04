<?php

namespace App\Console\Commands;

use App\Enums\RentalStatus;
use App\Models\Invoice;
use App\Models\Rental;
use App\Models\UtilityUsage;
use App\Services\InvoiceBuilderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateRentInvoices extends Command
{
    protected $signature = 'invoices:generate-rent {--date= : Any date within the billing month (defaults to today)} {--without-utilities : Generate rent-only invoices and leave utility usages unbilled}';

    protected $description = 'Generate monthly rent invoices for all active rentals, including unbilled utility usage by default.';

    public function handle(InvoiceBuilderService $builder): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::now();
        $periodStart = $date->copy()->startOfMonth();
        $periodEnd = $date->copy()->endOfMonth();
        $includeUtilities = ! $this->option('without-utilities');

        $created = 0;
        $skipped = 0;
        $utilityUsagesIncluded = 0;

        Rental::withoutGlobalScopes()
            ->where('status', RentalStatus::Active->value)
            ->with('unit')                              // eager-load (no N+1, unlike the old job)
            ->chunkById(100, function ($rentals) use (&$created, &$skipped, &$utilityUsagesIncluded, $builder, $periodStart, $periodEnd, $includeUtilities) {
                foreach ($rentals as $rental) {
                    // Dedup on the billing PERIOD, not created_at (fixes the old stale-date gap).
                    $exists = Invoice::withoutGlobalScopes()
                        ->where('rental_id', $rental->id)
                        ->whereDate('period_start', $periodStart->toDateString())
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    $usageIds = $includeUtilities
                        ? $this->unbilledUtilityUsageIds($rental, $periodStart, $periodEnd)
                        : [];

                    $builder->create([
                        'rental' => $rental,
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'issue_date' => $periodStart,
                        'include_rent' => true,
                        'usages' => $usageIds,
                    ]);

                    $utilityUsagesIncluded += count($usageIds);
                    $created++;
                }
            });

        $this->info("Rent invoices — created: {$created}, skipped (already billed): {$skipped}, utility usages included: {$utilityUsagesIncluded}.");

        return self::SUCCESS;
    }

    /**
     * Find existing usage rows that belong to this rental's unit and billing
     * period but have not yet been attached to any invoice line.
     *
     * @return array<int, int>
     */
    protected function unbilledUtilityUsageIds(Rental $rental, Carbon $periodStart, Carbon $periodEnd): array
    {
        return UtilityUsage::withoutGlobalScopes()
            ->where('rental_id', $rental->id)
            ->where('unit_id', $rental->unit_id)
            ->whereBetween('reading_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereDoesntHave('invoiceLine')
            ->orderBy('reading_date')
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
