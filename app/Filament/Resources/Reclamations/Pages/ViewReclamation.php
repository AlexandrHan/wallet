<?php

namespace App\Filament\Resources\Reclamations\Pages;

use App\Filament\Resources\Reclamations\ReclamationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReclamation extends ViewRecord
{
    protected static string $resource = ReclamationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
