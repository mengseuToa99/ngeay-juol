<?php

namespace App\Filament\Resources\MaintenanceRequestResource\Pages;

use App\Filament\Resources\MaintenanceRequestResource;
use App\Models\Unit;
use App\Support\ActiveProperty;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceRequest extends CreateRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['property_id']) && ActiveProperty::id()) {
            $data['property_id'] = ActiveProperty::id();
        }

        if (! empty($data['unit_id']) && empty($data['property_id'])) {
            $data['property_id'] = Unit::withoutGlobalScopes()->whereKey($data['unit_id'])->value('property_id');
        }

        if (empty($data['landlord_id']) && ! empty($data['property_id'])) {
            $data['landlord_id'] = \App\Models\Property::withoutGlobalScopes()->whereKey($data['property_id'])->value('landlord_id');
        }

        return $data;
    }
}
