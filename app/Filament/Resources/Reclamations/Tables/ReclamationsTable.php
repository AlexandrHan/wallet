<?php

namespace App\Filament\Resources\Reclamations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReclamationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('opened_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('settlement')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                IconColumn::make('has_replacement_fund')
                    ->boolean(),
                IconColumn::make('need_order_replacement')
                    ->boolean(),
                TextColumn::make('serial_number')
                    ->searchable(),
                TextColumn::make('dismantled_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('sent_to_service_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('sent_to_service_ttn')
                    ->searchable(),
                TextColumn::make('service_received_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('repaired_sent_back_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('repaired_sent_back_ttn')
                    ->searchable(),
                TextColumn::make('installed_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('replacement_sent_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('replacement_return_to')
                    ->searchable(),
                TextColumn::make('replacement_return_ttn')
                    ->searchable(),
                TextColumn::make('closed_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
