<?php

namespace App\Filament\Forms;

use App\Models\Setting;
use App\Support\LocationHelper;
use Filament\Forms;

class LocationFields
{
    /** @return array<int, \Filament\Forms\Components\Component> */
    public static function make(): array
    {
        if (! Setting::get('location_selects_enabled', true, 'admin')) {
            return [
                Forms\Components\TextInput::make('province')
                    ->label(__('City / Province'))
                    ->maxLength(255),
                Forms\Components\TextInput::make('district')
                    ->label(__('District (Khan / Srok)'))
                    ->maxLength(255),
                Forms\Components\TextInput::make('commune')
                    ->label(__('Commune (Sangkat / Khum)'))
                    ->maxLength(255),
                Forms\Components\TextInput::make('village')
                    ->label(__('Village (Phum)'))
                    ->maxLength(255),
            ];
        }

        return [
            Forms\Components\Select::make('province')
                ->label(__('City / Province'))
                ->options(LocationHelper::getProvinces())
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => [
                    $set('district', null),
                    $set('commune', null),
                    $set('village', null),
                ])
                ->extraAlpineAttributes(fn (string $operation) => $operation === 'create' ? [
                    'x-init' => self::provinceAutoFillScript(),
                ] : []),

            Forms\Components\Select::make('district')
                ->label(__('District (Khan / Srok)'))
                ->options(fn (Forms\Get $get) => LocationHelper::getDistricts($get('province')))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => [
                    $set('commune', null),
                    $set('village', null),
                ])
                ->disabled(fn (Forms\Get $get) => empty($get('province'))),

            Forms\Components\Select::make('commune')
                ->label(__('Commune (Sangkat / Khum)'))
                ->options(fn (Forms\Get $get) => LocationHelper::getCommunes($get('province'), $get('district')))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => [
                    $set('village', null),
                ])
                ->disabled(fn (Forms\Get $get) => empty($get('district'))),

            Forms\Components\Select::make('village')
                ->label(__('Village (Phum)'))
                ->options(fn (Forms\Get $get) => LocationHelper::getVillages($get('province'), $get('district'), $get('commune')))
                ->searchable()
                ->disabled(fn (Forms\Get $get) => empty($get('commune'))),
        ];
    }

    private static function provinceAutoFillScript(): string
    {
        return <<<'JS'
            if (!$wire.get("data.province")) {
                fetch("http://ip-api.com/json/")
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.countryCode === "KH") {
                            const provinceMap = {
                                "phnom penh": "Phnom Penh Capital",
                                "siem reap": "Siemreap",
                                "siemreap": "Siemreap",
                                "sihanoukville": "Preah Sihanouk",
                                "preah sihanouk": "Preah Sihanouk",
                                "banteay meanchey": "Banteay Meanchey",
                                "battambang": "Battambang",
                                "kampong cham": "Kampong Cham",
                                "kampong chhnang": "Kampong Chhnang",
                                "kampong speu": "Kampong Speu",
                                "kampong thom": "Kampong Thom",
                                "kampot": "Kampot",
                                "kandal": "Kandal",
                                "koh kong": "Koh Kong",
                                "kratie": "Kratie",
                                "mondulkiri": "Mondul Kiri",
                                "mondul kiri": "Mondul Kiri",
                                "preah vihear": "Preah Vihear",
                                "prey veng": "Prey Veng",
                                "pursat": "Pursat",
                                "ratanakiri": "Ratanak Kiri",
                                "ratanak kiri": "Ratanak Kiri",
                                "stung treng": "Stung Treng",
                                "svay rieng": "Svay Rieng",
                                "takeo": "Takeo",
                                "oddar meanchey": "Oddar Meanchey",
                                "ouddar meanchey": "Oddar Meanchey",
                                "kep": "Kep",
                                "pailin": "Pailin",
                                "tboung khmum": "Tboung Khmum"
                            };
                            const region = (data.regionName || data.city || "").toLowerCase().trim();
                            const matched = provinceMap[region];
                            if (matched) {
                                $wire.set("data.province", matched);
                            }
                        }
                    })
                    .catch(err => console.error("Geolocation fetch failed:", err));
            }
        JS;
    }
}
