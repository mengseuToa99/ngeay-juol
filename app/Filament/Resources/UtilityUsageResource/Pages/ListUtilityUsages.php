<?php

namespace App\Filament\Resources\UtilityUsageResource\Pages;

use App\Filament\Resources\UtilityUsageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Http\Controllers\UtilityExportController;
use Illuminate\Http\Request;

class ListUtilityUsages extends ListRecords
{
    protected static string $resource = UtilityUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('export')
                ->label(__('Export Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->modalSubmitActionLabel(__('Generate Export'))
                ->form([
                    Forms\Components\Select::make('time_period')
                        ->label(__('Time Period'))
                        ->options([
                            'all' => __('All Time'),
                            'this_year' => __('This Year'),
                            'last_year' => __('Last Year'),
                            'custom' => __('Custom Range'),
                        ])
                        ->default('all')
                        ->live()
                        ->required(),
                    Forms\Components\DatePicker::make('from_date')
                        ->label(__('From Date'))
                        ->visible(fn (Forms\Get $get) => $get('time_period') === 'custom')
                        ->required(fn (Forms\Get $get) => $get('time_period') === 'custom'),
                    Forms\Components\DatePicker::make('until_date')
                        ->label(__('Until Date'))
                        ->visible(fn (Forms\Get $get) => $get('time_period') === 'custom')
                        ->required(fn (Forms\Get $get) => $get('time_period') === 'custom'),
                    Forms\Components\CheckboxList::make('utility_types')
                        ->label(__('Utility Type'))
                        ->options(function () {
                            $propertyId = \App\Support\ActiveProperty::id();
                            if (!$propertyId) {
                                return [];
                            }
                            return ['all' => __('All')] + \App\Models\PropertyUtility::where('property_id', $propertyId)
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->default(['all'])
                        ->required(),
                    Forms\Components\Radio::make('format')
                        ->label(__('Export Format'))
                        ->options([
                            'csv' => 'CSV',
                            'xlsx' => 'Excel (.xlsx)',
                            'pdf' => 'PDF',
                        ])
                        ->default('csv')
                        ->inline()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $propertyId = \App\Support\ActiveProperty::id();
                    if (!$propertyId) {
                        Notification::make()
                            ->title(__('Error'))
                            ->body(__('No active property selected.'))
                            ->danger()
                            ->send();
                        return null;
                    }

                    // Call API Controller synchronously to return download
                    $request = new Request($data);
                    return app(UtilityExportController::class)->export($request, $propertyId);
                }),
        ];
    }
}
