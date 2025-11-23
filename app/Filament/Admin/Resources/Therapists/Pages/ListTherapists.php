<?php

namespace App\Filament\Admin\Resources\Therapists\Pages;

use App\Filament\Admin\Resources\Therapists\TherapistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTherapists extends ListRecords
{
    protected static string $resource = TherapistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
