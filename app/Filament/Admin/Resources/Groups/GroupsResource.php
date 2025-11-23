<?php

namespace App\Filament\Admin\Resources\Groups;

use App\Filament\Admin\Resources\Groups\Pages\CreateGroups;
use App\Filament\Admin\Resources\Groups\Pages\EditGroups;
use App\Filament\Admin\Resources\Groups\Pages\ListGroups;
use App\Filament\Admin\Resources\Groups\Schemas\GroupsForm;
use App\Filament\Admin\Resources\Groups\Tables\GroupsTable;
use App\Models\Group;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class GroupsResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'Available Groups ';

    public static function form(Schema $schema): Schema
    {
        return GroupsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GroupsTable::configure($table);
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
            'index' => ListGroups::route('/'),
            'create' => CreateGroups::route('/create'),
            'edit' => EditGroups::route('/{record}/edit'),
        ];
    }
}
