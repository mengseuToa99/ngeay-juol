<?php

namespace App\Filament\Resources;

use App\Enums\BillingType;
use App\Enums\ReadingType;
use App\Filament\Concerns\ScopesToActiveProperty;
use App\Filament\Resources\PropertyUtilityResource\Pages;
use App\Filament\Resources\PropertyUtilityResource\RelationManagers;
use App\Models\PropertyUtility;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\UtilityUsage;
use App\Models\UtilityWaiver;
use App\Support\ActiveProperty;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The per-property utility *catalog* (Electricity, Water, … with their rates and
 * billing rules) — what ManageUtilities used to manage inside the property
 * workspace. Distinct from UtilityUsageResource, which holds metered readings.
 */
class PropertyUtilityResource extends Resource
{
    use ScopesToActiveProperty;

    protected static ?string $model = PropertyUtility::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?int $navigationSort = 5;

    protected static function propertyContextFallbackGroup(): ?string
    {
        return 'Utilities';
    }

    public static function getNavigationLabel(): string
    {
        return __('Utilities');
    }

    public static function getModelLabel(): string
    {
        return __('Utility');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('property_id')
                ->relationship('property', 'name')
                ->default(fn () => ActiveProperty::id())
                ->hidden(fn () => ActiveProperty::id() !== null)
                ->dehydrated()
                ->searchable()->preload()
                ->required(fn () => ActiveProperty::id() === null),
            Forms\Components\Select::make('name')
                ->label(__('Utility name'))
                ->options(fn (?PropertyUtility $record) => static::utilityOptionsForRecord($record))
                ->default('Electricity')
                ->searchable()
                ->native(false)
                ->selectablePlaceholder(false)
                ->required()
                ->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set) => static::applyUtilityDefaults($state, $set)),
            Forms\Components\TextInput::make('unit_of_measure')
                ->label(__('Unit of measure'))
                ->required()
                ->default('kWh'),
            Forms\Components\Select::make('billing_type')->options(BillingType::class)->default(BillingType::Metered)->required(),
            Forms\Components\TextInput::make('rate')->numeric()->prefix(fn () => Money::activeSymbol())->step(0.0001)->required()
                ->helperText(__('Per unit (metered) or fixed amount (flat).')),
            Forms\Components\TextInput::make('provider')->placeholder('e.g. EDC, PPWSA'),
            Forms\Components\TextInput::make('account_ref')->label(__('Account #')),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->formatStateUsing(fn ($state) => static::utilityLabel((string) $state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('billing_type')->badge(),
                Tables\Columns\TextColumn::make('rate')
                    ->formatStateUsing(fn ($state, PropertyUtility $record) => Money::formatForRecord($state, $record)),
                Tables\Columns\TextColumn::make('unit_of_measure')->label(__('Unit')),
                Tables\Columns\TextColumn::make('provider')->placeholder('—')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    static::initializeReadingsAction(),
                    static::addWaiverAction(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->icon('heroicon-m-ellipsis-vertical')->label(null)->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** @return array<string, string> */
    protected static function utilityOptions(): array
    {
        return [
            'Electricity' => __('Electricity'),
            'Water' => __('Water'),
            'Gas' => __('Gas'),
            'Internet' => __('Internet'),
            'Trash' => __('Trash'),
            'Cleaning' => __('Cleaning'),
            'Parking' => __('Parking'),
            'Security' => __('Security'),
            'Other' => __('Other'),
        ];
    }

    /** @return array<string, string> */
    protected static function utilityOptionsForRecord(?PropertyUtility $record): array
    {
        $options = static::utilityOptions();
        $name = (string) ($record?->name ?? '');

        if ($name !== '' && ! array_key_exists($name, $options)) {
            $options[$name] = $name;
        }

        return $options;
    }

    protected static function utilityLabel(string $name): string
    {
        return static::utilityOptions()[$name] ?? $name;
    }

    protected static function applyUtilityDefaults(mixed $state, Forms\Set $set): void
    {
        $defaults = match ((string) $state) {
            'Electricity' => ['unit' => 'kWh', 'billing' => BillingType::Metered],
            'Water' => ['unit' => 'm³', 'billing' => BillingType::Metered],
            'Gas' => ['unit' => 'm³', 'billing' => BillingType::Metered],
            'Internet' => ['unit' => 'month', 'billing' => BillingType::Flat],
            'Trash' => ['unit' => 'month', 'billing' => BillingType::Flat],
            'Cleaning' => ['unit' => 'month', 'billing' => BillingType::Flat],
            'Parking' => ['unit' => 'month', 'billing' => BillingType::Flat],
            'Security' => ['unit' => 'month', 'billing' => BillingType::Flat],
            default => ['unit' => 'unit', 'billing' => BillingType::Flat],
        };

        $set('unit_of_measure', $defaults['unit']);
        $set('billing_type', $defaults['billing']->value);
    }

    protected static function addWaiverAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('addWaiver')
            ->label(__('Add waiver'))
            ->icon('heroicon-o-receipt-percent')
            ->color('warning')
            ->modalWidth('lg')
            ->modalHeading(fn ($record) => __('Add waiver — :utility', ['utility' => $record->name]))
            ->modalSubmitActionLabel(__('Save waiver'))
            ->form(function ($record): array {
                $propertyId = $record->property_id;

                return [
                    Forms\Components\Toggle::make('waived')->default(true)
                        ->label(__('Waived'))
                        ->helperText(__('On = this utility is not charged for the scope below.')),

                    Forms\Components\Toggle::make('apply_all')->default(false)
                        ->label(__('Apply to all rooms'))
                        ->helperText(__('Creates one property-wide waiver (ignores room selection below).'))
                        ->live(),

                    Forms\Components\CheckboxList::make('unit_ids')
                        ->label(__('Select rooms'))
                        ->options(fn () => Unit::where('property_id', $propertyId)
                            ->orderBy('room_number')
                            ->pluck('room_number', 'id'))
                        ->columns(2)
                        ->searchable()
                        ->bulkToggleable()
                        ->hidden(fn (Forms\Get $get) => $get('apply_all')),
                ];
            })
            ->action(function ($record, array $data): void {
                $applyAll = $data['apply_all'] ?? false;
                $waived = $data['waived'] ?? true;
                $base = [
                    'property_utility_id' => $record->getKey(),
                    'property_id' => $record->property_id,
                    'created_by_id' => auth()->id(),
                    'waived' => $waived,
                ];

                $count = 0;

                if ($applyAll) {
                    // One property-wide waiver (no unit/rental scope)
                    UtilityWaiver::create($base);
                    $count = 1;
                } else {
                    $unitIds = $data['unit_ids'] ?? [];
                    if (empty($unitIds)) {
                        // No rooms selected → property-wide
                        UtilityWaiver::create($base);
                        $count = 1;
                    } else {
                        foreach ($unitIds as $unitId) {
                            UtilityWaiver::create(array_merge($base, [
                                'unit_id' => $unitId,
                            ]));
                            $count++;
                        }
                    }
                }

                Notification::make()
                    ->title(trans_choice(':count waiver added|:count waivers added', $count, ['count' => $count]))
                    ->success()
                    ->send();
            });
    }

    protected static function initializeReadingsAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('initializeReadings')
            ->label(__('Initialize readings'))
            ->icon('heroicon-o-bolt')
            ->color('warning')
            ->visible(fn ($record) => $record->billing_type === BillingType::Metered)
            ->modalWidth('lg')
            ->modalHeading(fn ($record) => __('Initialize readings — :utility', ['utility' => $record->name]))
            ->modalSubmitActionLabel(__('Initialize'))
            ->form(function ($record): array {
                $units = Unit::where('property_id', $record->property_id)
                    ->orderBy('room_number')
                    ->get();

                if ($units->isEmpty()) {
                    return [
                        Forms\Components\Placeholder::make('no_units')
                            ->label('')
                            ->content(__('No rooms are set up for this property yet.')),
                    ];
                }

                $existingUnitIds = UtilityUsage::where('property_utility_id', $record->id)
                    ->pluck('unit_id')
                    ->toArray();

                $schema = [
                    Forms\Components\DatePicker::make('reading_date')
                        ->label(__('Reading date'))
                        ->default(now())
                        ->required()
                        ->maxDate(now()),
                ];

                foreach ($units as $unit) {
                    $hasReading = in_array($unit->id, $existingUnitIds);
                    $uom = $record->unit_of_measure;

                    $input = Forms\Components\TextInput::make("units.{$unit->id}")
                        ->label(__('Room :room', ['room' => $unit->room_number]))
                        ->numeric()
                        ->minValue(0)
                        ->step('0.001')
                        ->maxValue(999999999)
                        ->suffix($uom);

                    if ($hasReading) {
                        $latest = UtilityUsage::where('unit_id', $unit->id)
                            ->where('property_utility_id', $record->id)
                            ->orderByDesc('reading_date')
                            ->orderByDesc('id')
                            ->first();
                        $latestVal = $latest ? rtrim(rtrim(number_format((float) $latest->new_reading, 3), '0'), '.') : '';

                        $input->disabled()
                            ->dehydrated(false)
                            ->helperText(__('Reading already exists (:value)', ['value' => $latestVal]));
                    } else {
                        $input->helperText(__('Sets the starting baseline (no consumption billed).'));
                    }

                    $schema[] = $input;
                }

                return $schema;
            })
            ->action(function ($record, array $data): void {
                $units = Unit::where('property_id', $record->property_id)
                    ->with('activeRental')
                    ->get()
                    ->keyBy('id');

                $saved = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $record, $units): int {
                    $count = 0;
                    $unitsData = $data['units'] ?? [];

                    $existingUnitIds = UtilityUsage::where('property_utility_id', $record->id)
                        ->pluck('unit_id')
                        ->toArray();

                    foreach ($unitsData as $unitId => $value) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $unitId = (int) $unitId;
                        if (in_array($unitId, $existingUnitIds)) {
                            continue;
                        }
                        $unit = $units->get($unitId);
                        if (! $unit) {
                            continue;
                        }

                        UtilityUsage::create([
                            'unit_id' => $unitId,
                            'property_utility_id' => $record->getKey(),
                            'rental_id' => $unit->activeRental?->getKey(),
                            'reading_type' => ReadingType::Actual->value,
                            'reading_date' => $data['reading_date'],
                            'old_reading' => $value,
                            'new_reading' => $value,
                            'amount_used' => 0,
                            'recorded_by_id' => auth()->id(),
                        ]);
                        $count++;
                    }

                    return $count;
                });

                Notification::make()
                    ->title($saved
                        ? __(':count opening reading(s) initialized', ['count' => $saved])
                        : __('No readings entered.'))
                    ->{$saved ? 'success' : 'warning'}()
                    ->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\WaiversRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPropertyUtilities::route('/'),
            'create' => Pages\CreatePropertyUtility::route('/create'),
            'edit' => Pages\EditPropertyUtility::route('/{record}/edit'),
        ];
    }
}
