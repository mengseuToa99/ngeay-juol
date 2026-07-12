<?php

namespace App\Services;

use App\Enums\MoveInReadinessStatus;
use App\Enums\RentalStatus;
use App\Models\Rental;
use Illuminate\Support\Facades\DB;

class CompleteMoveInAction
{
    public function __invoke(Rental $rental, ?int $actorId = null): Rental
    {
        return DB::transaction(function () use ($rental, $actorId) {
            $rental = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();
            $readiness = app(MoveInRuleService::class)->readiness($rental);
            if (! $readiness['ready']) throw new \DomainException('Move-in requirements are not satisfied.');
            if ($rental->move_in_status === MoveInReadinessStatus::Active) return $rental;
            $rental->forceFill(['status' => RentalStatus::Active, 'move_in_status' => MoveInReadinessStatus::Active, 'moved_in_at' => now(), 'moved_in_by_id' => $actorId])->saveQuietly();
            $rental->occupyUnit();
            return $rental->refresh();
        });
    }
}
