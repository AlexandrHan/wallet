<?php

namespace App\Filament\Resources\Reclamations\Pages;

use App\Filament\Resources\Reclamations\ReclamationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditReclamation extends EditRecord
{
    protected static string $resource = ReclamationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
