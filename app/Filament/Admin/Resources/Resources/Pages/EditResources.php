<?php

namespace App\Filament\Admin\Resources\Resources\Pages;

use App\Filament\Admin\Resources\Resources\ResourcesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditResources extends EditRecord
{
    protected static string $resource = ResourcesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
