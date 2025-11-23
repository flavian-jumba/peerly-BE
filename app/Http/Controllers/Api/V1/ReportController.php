<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // GET /api/reports
    public function index(Request $request)
    {
        // Only admins typically list all reports
        return Report::with(['reporter', 'reportedUser', 'message', 'group'])->paginate(20);
    }

    // GET /api/reports/{id}
    public function show($id)
    {
        return Report::with(['reporter', 'reportedUser', 'message', 'group'])->findOrFail($id);
    }

    // POST /api/reports
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reporter_id'      => 'required|exists:users,id',
            'reported_user_id' => 'nullable|exists:users,id',
            'message_id'       => 'nullable|exists:messages,id',
            'group_id'         => 'nullable|exists:groups,id',
            'reason'           => 'required|string|max:255',
            'details'          => 'nullable|string|max:1000',
        ]);
        return Report::create($validated);
    }

    // PUT/PATCH /api/reports/{id}
    public function update(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        $report->update($request->only(['reason', 'details', 'resolved']));
        return $report->load(['reporter', 'reportedUser', 'message', 'group']);
    }

    // DELETE /api/reports/{id}
    public function destroy($id)
    {
        $report = Report::findOrFail($id);
        $report->delete();
        return response()->json(['message' => 'Report deleted.']);
    }
}
