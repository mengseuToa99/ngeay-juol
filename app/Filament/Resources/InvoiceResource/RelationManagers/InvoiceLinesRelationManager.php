<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Enums\InvoiceLineType;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Line items';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('line_type')
                ->options(InvoiceLineType::class)
                ->default(InvoiceLineType::AdHoc)
                ->required(),
            Forms\Components\TextInput::make('description')->required(),
            Forms\Components\TextInput::make('quantity')->numeric(),
            Forms\Components\TextInput::make('unit_price')->numeric()->prefix(fn () => Money::activeSymbol()),
            Forms\Components\TextInput::make('amount')->numeric()->prefix(fn () => Money::activeSymbol())->required(),
            Forms\Components\Toggle::make('is_waived'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('line_type')->badge(),
                Tables\Columns\TextColumn::make('description')->wrap(),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('unit_price')
                    ->formatStateUsing(fn ($state, $record) => Money::formatForRecord($state, $record)),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn ($state, $record) => Money::formatForRecord($state, $record)),
                Tables\Columns\IconColumn::make('is_waived')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->icon('heroicon-m-ellipsis-vertical')->label(null)->color('gray'),
            ]);
    }
}
