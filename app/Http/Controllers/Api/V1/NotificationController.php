<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notifications;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /api/notifications
    public function index(Request $request)
    {
        $userId = $request->user()->id ?? $request->input('user_id');
        return Notifications::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    // GET /api/notifications/{id}
    public function show($id)
    {
        return Notifications::findOrFail($id);
    }

    // POST /api/notifications
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'  => 'required|exists:users,id',
            'type'     => 'required|string|max:50',
            'title'    => 'nullable|string|max:255',
            'message'  => 'required|string',
        ]);
        return Notifications::create($validated);
    }

    // PUT/PATCH /api/notifications/{id}
    public function update(Request $request, $id)
    {
        $notification = Notifications::findOrFail($id);
        $notification->update($request->only(['read', 'read_at']));
        return $notification;
    }

    // PUT /api/v1/notifications/{id}/mark-read - Mark a notification as read
    public function markAsRead($id)
    {
        $notification = Notifications::findOrFail($id);
        $notification->update([
            'read' => true,
            'read_at' => now(),
        ]);
        return $notification;
    }

    // GET /api/v1/notifications/unread-count - Get unread notifications count
    public function unreadCount(Request $request)
    {
        $userId = $request->user()->id ?? $request->input('user_id');
        
        $count = Notifications::where('user_id', $userId)
            ->where('read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    // PUT /api/v1/notifications/mark-all-read - Mark all notifications as read
    public function markAllAsRead(Request $request)
    {
        $userId = $request->user()->id ?? $request->input('user_id');

        Notifications::where('user_id', $userId)
            ->where('read', false)
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    // DELETE /api/notifications/{id}
    public function destroy($id)
    {
        $notification = Notifications::findOrFail($id);
        $notification->delete();
        return response()->json(['message' => 'Notification deleted.']);
    }
}
