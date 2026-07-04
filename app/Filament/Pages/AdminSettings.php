<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AdminSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.admin-settings';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }

    public function getTitle(): string
    {
        return __('Admin Settings');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'location_selects_enabled' => Setting::get('location_selects_enabled', true, 'admin'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Location Input'))
                    ->description(__('Controls province, district, commune, and village fields when creating or editing landlords and tenants.'))
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Forms\Components\Toggle::make('location_selects_enabled')
                            ->label(__('Use location dropdowns'))
                            ->helperText(__('Turn this off to allow admins and landlords to type the four location fields manually.'))
                            ->default(true)
                            ->inline(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::set(
            'location_selects_enabled',
            (bool) ($state['location_selects_enabled'] ?? false),
            'bool',
            'admin',
        );

        Notification::make()
            ->success()
            ->title(__('Admin settings saved'))
            ->send();
    }
}
