<?php

namespace App\Filament\Admin\Resources\Therapists\Pages;

use App\Filament\Admin\Resources\Therapists\TherapistResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTherapist extends EditRecord
{
    protected static string $resource = TherapistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
