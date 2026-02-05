<?php

namespace App\Filament\Resources\Reclamations;

use App\Filament\Resources\Reclamations\Pages\CreateReclamation;
use App\Filament\Resources\Reclamations\Pages\EditReclamation;
use App\Filament\Resources\Reclamations\Pages\ListReclamations;
use App\Filament\Resources\Reclamations\Pages\ViewReclamation;
use App\Filament\Resources\Reclamations\Schemas\ReclamationForm;
use App\Filament\Resources\Reclamations\Schemas\ReclamationInfolist;
use App\Filament\Resources\Reclamations\Tables\ReclamationsTable;
use App\Models\Reclamation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReclamationResource extends Resource
{
    protected static ?string $model = Reclamation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return ReclamationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReclamationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReclamationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReclamations::route('/'),
            'create' => CreateReclamation::route('/create'),
            'view' => ViewReclamation::route('/{record}'),
            'edit' => EditReclamation::route('/{record}/edit'),
        ];
    }
}
