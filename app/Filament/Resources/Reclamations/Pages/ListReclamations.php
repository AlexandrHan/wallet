<?php

namespace App\Filament\Resources\Reclamations\Pages;

use App\Filament\Resources\Reclamations\ReclamationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReclamations extends ListRecords
{
    protected static string $resource = ReclamationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
