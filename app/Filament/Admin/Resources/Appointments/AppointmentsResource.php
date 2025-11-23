<?php

namespace App\Filament\Admin\Resources\Appointments;

use App\Filament\Admin\Resources\Appointments\Pages\CreateAppointments;
use App\Filament\Admin\Resources\Appointments\Pages\EditAppointments;
use App\Filament\Admin\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Admin\Resources\Appointments\Schemas\AppointmentsForm;
use App\Filament\Admin\Resources\Appointments\Tables\AppointmentsTable;
use App\Models\Appointment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AppointmentsResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return AppointmentsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppointmentsTable::configure($table);
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
            'index' => ListAppointments::route('/'),
            'create' => CreateAppointments::route('/create'),
            'edit' => EditAppointments::route('/{record}/edit'),
        ];
    }
}
