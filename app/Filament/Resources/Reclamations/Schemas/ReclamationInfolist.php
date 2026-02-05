<?php

namespace App\Filament\Resources\Reclamations\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReclamationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('opened_at')
                    ->date(),
                TextEntry::make('last_name'),
                TextEntry::make('settlement'),
                TextEntry::make('phone'),
                IconEntry::make('has_replacement_fund')
                    ->boolean(),
                IconEntry::make('need_order_replacement')
                    ->boolean(),
                TextEntry::make('serial_number'),
                TextEntry::make('dismantled_at')
                    ->date(),
                TextEntry::make('sent_to_service_at')
                    ->date(),
                TextEntry::make('sent_to_service_ttn'),
                TextEntry::make('service_received_at')
                    ->date(),
                TextEntry::make('repaired_sent_back_at')
                    ->date(),
                TextEntry::make('repaired_sent_back_ttn'),
                TextEntry::make('installed_at')
                    ->date(),
                TextEntry::make('replacement_sent_at')
                    ->date(),
                TextEntry::make('replacement_return_to'),
                TextEntry::make('replacement_return_ttn'),
                TextEntry::make('closed_at')
                    ->date(),
                TextEntry::make('status'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
