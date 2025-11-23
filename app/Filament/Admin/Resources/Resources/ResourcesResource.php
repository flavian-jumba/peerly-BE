<?php

namespace App\Filament\Admin\Resources\Resources;

use App\Filament\Admin\Resources\Resources\Pages\CreateResources;
use App\Filament\Admin\Resources\Resources\Pages\EditResources;
use App\Filament\Admin\Resources\Resources\Pages\ListResources;
use App\Filament\Admin\Resources\Resources\Schemas\ResourcesForm;
use App\Filament\Admin\Resources\Resources\Tables\ResourcesTable;
use App\Models\Resources;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ResourcesResource extends Resource
{
    protected static ?string $model = Resources::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return ResourcesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResourcesTable::configure($table);
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
            'index' => ListResources::route('/'),
            'create' => CreateResources::route('/create'),
            'edit' => EditResources::route('/{record}/edit'),
        ];
    }
}
