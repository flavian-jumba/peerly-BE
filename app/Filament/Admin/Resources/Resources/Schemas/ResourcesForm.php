<?php

namespace App\Filament\Admin\Resources\Resources\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ResourcesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('type')
                    ->required()
                    ->default('article'),
                TextInput::make('url')
                    ->url(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('content')
                    ->columnSpanFull(),
                TextInput::make('tags'),
            ]);
    }
}
