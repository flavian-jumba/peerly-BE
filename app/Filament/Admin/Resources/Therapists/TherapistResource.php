<?php

namespace App\Filament\Admin\Resources\Therapists;

use App\Filament\Admin\Resources\Therapists\Pages\CreateTherapist;
use App\Filament\Admin\Resources\Therapists\Pages\EditTherapist;
use App\Filament\Admin\Resources\Therapists\Pages\ListTherapists;
use App\Filament\Admin\Resources\Therapists\Schemas\TherapistForm;
use App\Filament\Admin\Resources\Therapists\Tables\TherapistsTable;
use App\Models\Therapist;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class TherapistResource extends Resource
{
    protected static ?string $model = Therapist::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return TherapistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TherapistsTable::configure($table);
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
            'index' => ListTherapists::route('/'),
            'create' => CreateTherapist::route('/create'),
            'edit' => EditTherapist::route('/{record}/edit'),
        ];
    }
}
