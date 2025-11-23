<?php

namespace App\Filament\Admin\Resources\Resources\Pages;

use App\Filament\Admin\Resources\Resources\ResourcesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResources extends ListRecords
{
    protected static string $resource = ResourcesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
