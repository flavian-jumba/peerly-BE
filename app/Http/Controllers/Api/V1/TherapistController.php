<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Therapist;
use Illuminate\Http\Request;

class TherapistController extends Controller
{
    // GET /api/therapists
    public function index()
    {
        return Therapist::paginate(20);
    }

    // GET /api/therapists/{id}
    public function show($id)
    {
        return Therapist::findOrFail($id);
    }

    // POST /api/therapists
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email'        => 'required|email|unique:therapists,email',
            'specialty'    => 'required|string|max:255',
            'bio'          => 'nullable|string|max:1000',
        ]);
        return Therapist::create($validated);
    }

    // PUT/PATCH /api/therapists/{id}
    public function update(Request $request, $id)
    {
        $therapist = Therapist::findOrFail($id);
        $therapist->update($request->only(['name', 'phone_number', 'email', 'specialty', 'bio']));
        return $therapist;
    }

    // DELETE /api/therapists/{id}
    public function destroy($id)
    {
        $therapist = Therapist::findOrFail($id);
        $therapist->delete();
        return response()->json(['message' => 'Therapist deleted.']);
    }
}
