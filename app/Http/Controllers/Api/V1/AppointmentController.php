<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Notifications;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    // GET /api/appointments
    public function index(Request $request)
    {
        $userId = $request->query('user_id');
        
        $query = Appointment::with(['user', 'therapist']);
        
        // If user_id is provided, filter by user
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        // For API, return paginated data
        $appointments = $query->orderBy('appointment_at', 'desc')->paginate(10);
        
        return response()->json($appointments);
    }

    // GET /api/appointments/{id}
    public function show($id)
    {
        return Appointment::with(['user', 'therapist'])->findOrFail($id);
    }

    // POST /api/appointments
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'therapist_id' => 'required|exists:therapists,id',
            'appointment_at' => 'required|date|after_or_equal:today',
            'duration_minutes' => 'nullable|integer|min:15|max:240',
            'status' => 'required|string|in:pending,confirmed,cancelled,completed',
            'notes' => 'nullable|string',
            'created_by' => 'nullable|string|in:user,admin,system',
        ]);

        // Set defaults
        $validated['duration_minutes'] = $validated['duration_minutes'] ?? 60;
        $validated['created_by'] = $validated['created_by'] ?? 'user';

        $requestedTime = Carbon::parse($validated['appointment_at']);
        $startTime = $requestedTime->copy();
        $endTime = $requestedTime->copy()->addMinutes($validated['duration_minutes']);

        // Check for therapist overlap with more accurate conflict detection
        $therapistOverlap = Appointment::where('therapist_id', $validated['therapist_id'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    // Existing appointment starts during new appointment
                    $q->whereRaw('appointment_at >= ? AND appointment_at < ?', [$startTime, $endTime]);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    // Existing appointment ends during new appointment
                    $q->whereRaw('DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) > ? AND DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) <= ?', 
                        [$startTime, $endTime]);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    // Existing appointment completely contains new appointment
                    $q->whereRaw('appointment_at <= ? AND DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) >= ?', 
                        [$startTime, $endTime]);
                });
            })
            ->exists();

        if ($therapistOverlap) {
            throw ValidationException::withMessages([
                'appointment_at' => ['The therapist already has an appointment during this time. Please choose a different time slot.'],
            ]);
        }

        // Check for user overlap
        $userOverlap = Appointment::where('user_id', $validated['user_id'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    $q->whereRaw('appointment_at >= ? AND appointment_at < ?', [$startTime, $endTime]);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    $q->whereRaw('DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) > ? AND DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) <= ?', 
                        [$startTime, $endTime]);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    $q->whereRaw('appointment_at <= ? AND DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) >= ?', 
                        [$startTime, $endTime]);
                });
            })
            ->exists();

        if ($userOverlap) {
             throw ValidationException::withMessages([
                'appointment_at' => ['You already have an appointment scheduled during this time. Please choose a different time slot.'],
            ]);
        }

        $appointment = Appointment::create($validated);

        // Create notification for the user
        $therapistName = $appointment->therapist->name;
        $appointmentTime = Carbon::parse($appointment->appointment_at)->format('F j, Y, g:i a');
        $duration = $appointment->duration_minutes;

        Notifications::create([
            'user_id' => $appointment->user_id,
            'type' => 'appointment_booked',
            'title' => 'Appointment Confirmed',
            'message' => "Your appointment with {$therapistName} is scheduled for {$appointmentTime} ({$duration} minutes). Status: {$appointment->status}.",
            'read' => false,
        ]);

        return $appointment->load(['user', 'therapist']);
    }

    // PUT/PATCH /api/appointments/{id}
    public function update(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);
        
        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'therapist_id' => 'sometimes|exists:therapists,id',
            'appointment_at' => 'sometimes|date',
            'duration_minutes' => 'sometimes|integer|min:15|max:240',
            'status' => 'sometimes|string|in:pending,confirmed,cancelled,completed',
            'notes' => 'nullable|string',
        ]);

        // If appointment time, therapist, or user is being changed, check for conflicts
        if (isset($validated['appointment_at']) || isset($validated['therapist_id']) || isset($validated['user_id']) || isset($validated['duration_minutes'])) {
            $userId = $validated['user_id'] ?? $appointment->user_id;
            $therapistId = $validated['therapist_id'] ?? $appointment->therapist_id;
            $appointmentAt = $validated['appointment_at'] ?? $appointment->appointment_at;
            $durationMinutes = $validated['duration_minutes'] ?? $appointment->duration_minutes ?? 60;

            $requestedTime = Carbon::parse($appointmentAt);
            $startTime = $requestedTime->copy();
            $endTime = $requestedTime->copy()->addMinutes($durationMinutes);

            // Check for therapist overlap (exclude current appointment)
            $therapistOverlap = Appointment::where('therapist_id', $therapistId)
                ->where('id', '!=', $id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->whereRaw('appointment_at >= ? AND appointment_at < ?', [$startTime, $endTime]);
                    })->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->whereRaw('DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) > ? AND DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) <= ?', 
                            [$startTime, $endTime]);
                    })->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->whereRaw('appointment_at <= ? AND DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) >= ?', 
                            [$startTime, $endTime]);
                    });
                })
                ->exists();

            if ($therapistOverlap) {
                throw ValidationException::withMessages([
                    'appointment_at' => ['The therapist already has an appointment during this time.'],
                ]);
            }

            // Check for user overlap (exclude current appointment)
            $userOverlap = Appointment::where('user_id', $userId)
                ->where('id', '!=', $id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->whereRaw('appointment_at >= ? AND appointment_at < ?', [$startTime, $endTime]);
                    })->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->whereRaw('DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) > ? AND DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) <= ?', 
                            [$startTime, $endTime]);
                    })->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->whereRaw('appointment_at <= ? AND DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) >= ?', 
                            [$startTime, $endTime]);
                    });
                })
                ->exists();

            if ($userOverlap) {
                throw ValidationException::withMessages([
                    'appointment_at' => ['The patient already has an appointment during this time.'],
                ]);
            }
        }

        $appointment->update($validated);
        return $appointment->load(['user', 'therapist']);
    }

    // DELETE /api/appointments/{id}
    public function destroy($id)
    {
        $appointment = Appointment::findOrFail($id);
        $appointment->delete();
        return response()->json(['message' => 'Appointment deleted.']);
    }
}
