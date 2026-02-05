<?php

namespace App\Filament\Resources\Reclamations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReclamationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('opened_at'),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('settlement')
                    ->required(),
                TextInput::make('phone')
                    ->tel(),
                Toggle::make('has_replacement_fund')
                    ->required(),
                Toggle::make('need_order_replacement')
                    ->required(),
                TextInput::make('serial_number'),
                DatePicker::make('dismantled_at'),
                DatePicker::make('sent_to_service_at'),
                TextInput::make('sent_to_service_ttn'),
                DatePicker::make('service_received_at'),
                DatePicker::make('repaired_sent_back_at'),
                TextInput::make('repaired_sent_back_ttn'),
                DatePicker::make('installed_at'),
                DatePicker::make('replacement_sent_at'),
                TextInput::make('replacement_return_to'),
                TextInput::make('replacement_return_ttn'),
                DatePicker::make('closed_at'),
                TextInput::make('status')
                    ->required()
                    ->default('new'),
                Textarea::make('note')
                    ->columnSpanFull(),
            ]);
    }
}
