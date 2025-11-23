<?php

namespace App\Filament\Admin\Resources\Reports\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('reporter_id')
                    ->relationship('reporter', 'name')
                    ->required(),
                Select::make('reported_user_id')
                    ->relationship('reportedUser', 'name'),
                Select::make('message_id')
                    ->relationship('message', 'id'),
                Select::make('group_id')
                    ->relationship('group', 'title'),
                TextInput::make('reason')
                    ->required(),
                Textarea::make('details')
                    ->columnSpanFull(),
                Toggle::make('resolved')
                    ->required(),
            ]);
    }
}
