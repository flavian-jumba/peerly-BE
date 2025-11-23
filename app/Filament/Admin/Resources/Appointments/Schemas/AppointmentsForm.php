<?php

namespace App\Filament\Admin\Resources\Appointments\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use App\Models\Appointment;
use Carbon\Carbon;

class AppointmentsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Patient')
                    ->helperText('Select the patient for this appointment'),
                Select::make('therapist_id')
                    ->relationship('therapist', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->helperText('Select the therapist for this appointment'),
                DateTimePicker::make('appointment_at')
                    ->required()
                    ->label('Appointment Date & Time')
                    ->minDate(now())
                    ->native(false)
                    ->seconds(false)
                    ->live()
                    ->helperText('Select date and time for the appointment')
                    ->rules([
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                if (!$value) {
                                    return;
                                }
                                
                                // Get form data
                                $data = request()->all();
                                $userId = $data['user_id'] ?? null;
                                $therapistId = $data['therapist_id'] ?? null;
                                $durationMinutes = $data['duration_minutes'] ?? 60;
                                $recordId = $data['id'] ?? null;
                                
                                if (!$userId || !$therapistId) {
                                    return;
                                }
                                
                                $appointmentTime = Carbon::parse($value);
                                $startTime = $appointmentTime->copy();
                                $endTime = $appointmentTime->copy()->addMinutes($durationMinutes);
                                
                                // Check for therapist conflicts
                                $therapistConflict = Appointment::where('therapist_id', $therapistId)
                                    ->where('id', '!=', $recordId ?? 0) // Exclude current record if editing
                                    ->where('status', '!=', 'cancelled')
                                    ->where(function ($query) use ($startTime, $endTime) {
                                        $query->where(function ($q) use ($startTime, $endTime) {
                                            // New appointment starts during existing appointment
                                            $q->whereRaw('appointment_at <= ? AND DATE_ADD(appointment_at, INTERVAL duration_minutes MINUTE) > ?', 
                                                [$startTime, $startTime]);
                                        })->orWhere(function ($q) use ($startTime, $endTime) {
                                            // New appointment ends during existing appointment
                                            $q->whereRaw('appointment_at < ? AND DATE_ADD(appointment_at, INTERVAL duration_minutes MINUTE) >= ?', 
                                                [$endTime, $endTime]);
                                        })->orWhere(function ($q) use ($startTime, $endTime) {
                                            // New appointment completely overlaps existing appointment
                                            $q->whereRaw('appointment_at >= ? AND DATE_ADD(appointment_at, INTERVAL duration_minutes MINUTE) <= ?', 
                                                [$startTime, $endTime]);
                                        });
                                    })
                                    ->exists();
                                
                                if ($therapistConflict) {
                                    $fail('The therapist already has an appointment during this time slot.');
                                    return;
                                }
                                
                                // Check for patient conflicts
                                $userConflict = Appointment::where('user_id', $userId)
                                    ->where('id', '!=', $recordId ?? 0)
                                    ->where('status', '!=', 'cancelled')
                                    ->where(function ($query) use ($startTime, $endTime) {
                                        $query->where(function ($q) use ($startTime, $endTime) {
                                            $q->whereRaw('appointment_at <= ? AND DATE_ADD(appointment_at, INTERVAL duration_minutes MINUTE) > ?', 
                                                [$startTime, $startTime]);
                                        })->orWhere(function ($q) use ($startTime, $endTime) {
                                            $q->whereRaw('appointment_at < ? AND DATE_ADD(appointment_at, INTERVAL duration_minutes MINUTE) >= ?', 
                                                [$endTime, $endTime]);
                                        })->orWhere(function ($q) use ($startTime, $endTime) {
                                            $q->whereRaw('appointment_at >= ? AND DATE_ADD(appointment_at, INTERVAL duration_minutes MINUTE) <= ?', 
                                                [$startTime, $endTime]);
                                        });
                                    })
                                    ->exists();
                                
                                if ($userConflict) {
                                    $fail('The patient already has an appointment during this time slot.');
                                }
                            };
                        },
                    ]),
                TextInput::make('duration_minutes')
                    ->numeric()
                    ->default(60)
                    ->required()
                    ->minValue(15)
                    ->maxValue(240)
                    ->suffix('minutes')
                    ->helperText('Duration of the appointment (15-240 minutes)'),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ])
                    ->required()
                    ->default('pending')
                    ->helperText('Current status of the appointment'),
                Select::make('created_by')
                    ->options([
                        'admin' => 'Admin',
                        'user' => 'User',
                        'system' => 'System',
                    ])
                    ->default('admin')
                    ->required()
                    ->helperText('Who created this appointment'),
                Textarea::make('notes')
                    ->rows(3)
                    ->helperText('Additional notes or special instructions'),
            ]);
    }
}
