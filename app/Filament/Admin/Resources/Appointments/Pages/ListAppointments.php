<?php

namespace App\Filament\Admin\Resources\Appointments\Pages;

use App\Filament\Admin\Resources\Appointments\AppointmentsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
