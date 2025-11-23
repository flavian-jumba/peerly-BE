<?php

namespace App\Filament\Admin\Resources\Appointments\Pages;

use App\Filament\Admin\Resources\Appointments\AppointmentsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAppointments extends EditRecord
{
    protected static string $resource = AppointmentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
