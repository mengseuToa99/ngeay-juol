<?php

namespace App\Livewire;

use App\Enums\UnitStatus;
use App\Models\Unit;
use App\Support\ActiveProperty;
use Livewire\Component;

/**
 * Simple room status list for mobile/PWA.
 * Shows all rooms for the active property with status badge and quick-action links.
 */
class SimpleRoomList extends Component
{
    public string $search = '';

    public function updatingSearch(): void
    {
        // no pagination to reset
    }

    public function render()
    {
        $propertyId = ActiveProperty::id();

        $rooms = $propertyId
            ? Unit::query()
                ->with(['activeRental.tenant'])
                ->where('property_id', $propertyId)
                ->when($this->search !== '', function ($q) {
                    $s = '%'.trim($this->search).'%';
                    $q->where('room_number', 'like', $s)
                      ->orWhereHas('activeRental', fn ($rq) =>
                          $rq->where('occupant_name', 'like', $s)
                             ->orWhereHas('tenant', fn ($tq) => $tq->where('name', 'like', $s))
                      );
                })
                ->orderBy('room_number')
                ->get()
            : collect();

        return view('livewire.simple-room-list', compact('rooms'));
    }
}
