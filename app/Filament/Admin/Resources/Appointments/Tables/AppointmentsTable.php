<?php

namespace App\Filament\Admin\Resources\Appointments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Patient')
                    ->description(fn ($record) => $record->user->email)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('therapist.name')
                    ->label('Therapist')
                    ->description(fn ($record) => $record->therapist->specialty ?? 'General')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('appointment_at')
                    ->label('Appointment Time')
                    ->dateTime('M d, Y h:i A')
                    ->sortable()
                    ->description(fn ($record) => 'Duration: ' . ($record->duration_minutes ?? 60) . ' min'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('created_by')
                    ->label('Created By')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'success',
                        'user' => 'info',
                        'system' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Booked On')
                    ->dateTime('M d, Y h:i A')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('notes')
                    ->limit(50)
                    ->toggleable()
                    ->tooltip(fn ($record) => $record->notes),
            ])
            ->filters([
                //
            ])
            ->defaultSort('appointment_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
