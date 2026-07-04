<?php

namespace App\Filament\Resources;

use App\Enums\UserStatus;
use App\Filament\Forms\LocationFields;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        // Follow the active-property sidebar group (labelled with the property's
        // name) like the utility and other property resources. Users aren't
        // property-scoped, so we only mirror the grouping — never the query —
        // and fall back to Administration when no property is active.
        return \App\Support\ActiveProperty::id() !== null
            ? \App\Support\ActiveProperty::NAV_GROUP
            : __('Administration');
    }

    public static function getModelLabel(): string
    {
        return __('Tenant');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tenant');
    }

    public static function getNavigationLabel(): string
    {
        return __('Tenant');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Account'))
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('email')->email()->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('username')
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText(__('Login for room/tenant accounts (no email required).')),
                    Forms\Components\TextInput::make('phone_number')->tel(),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        // the User 'hashed' cast hashes the plain value on save
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state)),
                    Forms\Components\Select::make('status')
                        ->options(UserStatus::class)
                        ->default(UserStatus::Active)
                        ->required(),
                ])->columns(2),

            Forms\Components\Section::make(__('Personal info'))
                ->schema([
                    Forms\Components\Select::make('gender')
                        ->options([
                            'male' => __('Male'),
                            'female' => __('Female'),
                            'other' => __('Other'),
                        ])
                        ->placeholder(__('Select gender')),
                    Forms\Components\DatePicker::make('dob')
                        ->label(__('Date of birth'))
                        ->maxDate(now()),
                    Forms\Components\TextInput::make('nationality')
                        ->placeholder(__('e.g. Khmer, Vietnamese')),
                ])->columns(3),

            Forms\Components\Section::make(__('Location'))
                ->schema(LocationFields::make())
                ->columns(2),

            Forms\Components\Section::make(__('Tenant details'))
                ->description(__('Extra info for tenants — occupation, emergency contacts, guarantor.'))
                ->relationship('tenantProfile')
                ->schema([
                    Forms\Components\TextInput::make('id_card_number')->label(__('ID card number')),
                    Forms\Components\TextInput::make('occupation'),
                    Forms\Components\TextInput::make('workplace')
                        ->placeholder(__('e.g. company name')),
                    Forms\Components\TextInput::make('monthly_income')
                        ->numeric()->prefix('$'),
                    Forms\Components\DatePicker::make('move_in_date')
                        ->label(__('Move-in date')),

                    Forms\Components\Fieldset::make(__('Emergency contact'))
                        ->schema([
                            Forms\Components\TextInput::make('emergency_contact_name')->label(__('Name')),
                            Forms\Components\TextInput::make('emergency_contact_phone')->label(__('Phone'))->tel(),
                            Forms\Components\TextInput::make('emergency_contact_relationship')
                                ->label(__('Relationship'))
                                ->placeholder(__('e.g. mother, brother')),
                        ])->columns(3),

                    Forms\Components\Fieldset::make(__('Guarantor'))
                        ->schema([
                            Forms\Components\TextInput::make('guarantor_name')->label(__('Name')),
                            Forms\Components\TextInput::make('guarantor_phone')->label(__('Phone'))->tel(),
                            Forms\Components\TextInput::make('guarantor_id_number')->label(__('ID number')),
                            Forms\Components\TextInput::make('guarantor_address')->label(__('Address')),
                        ])->columns(2),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('Notes'))
                        ->placeholder(__('Private notes about this tenant'))
                        ->columnSpanFull(),
                ])->columns(2)
                ->collapsed(),

            Forms\Components\Section::make(__('Access'))
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable(),
                    Forms\Components\Select::make('manages_landlord_id')
                        ->label(__('Manages landlord'))
                        ->relationship('managesLandlord', 'name')
                        ->searchable()
                        ->helperText(__('For a landlord_manager: the landlord they act on behalf of.')),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('username')->searchable()->copyable()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('email')->searchable()->copyable()->placeholder('—'),
                Tables\Columns\TextColumn::make('roles.name')->badge()->label(__('Roles')),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')->relationship('roles', 'name'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
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

    /**
     * Landlords/managers see only "their" users — tenants they created or who rent
     * one of their units. Platform staff (super_admin/support) see everyone.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->isPlatformStaff()) {
            $landlordId = $user->effectiveLandlordId();

            $query->where(function (Builder $w) use ($user, $landlordId) {
                $w->where('created_by_id', $user->getKey());

                if ($landlordId !== null) {
                    $w->orWhere('created_by_id', $landlordId)
                        ->orWhereHas('rentalsAsTenant', fn (Builder $r) => $r->where('landlord_id', $landlordId));
                }
            });
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
