<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    // GET /api/conversations
    public function index(Request $request)
    {
        // Display all conversations for the authenticated user
        $userId = $request->user()->id;
        return Conversation::whereHas('participants', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->with('participants')
            ->orderBy('updated_at', 'desc')
            ->paginate(20);
    }

    // GET /api/conversations/{id}
    public function show($id)
    {
        return Conversation::with('participants', 'messages.user')->findOrFail($id);
    }

    // POST /api/conversations
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:2',
            'user_ids.*' => 'exists:users,id',
        ]);

        // Check for duplicate conversations (one-on-one)
        // Find conversations where ALL specified users are participants
        $userIds = $validated['user_ids'];
        $conv = Conversation::whereHas('participants', function ($q) use ($userIds) {
            $q->whereIn('users.id', $userIds);
        }, '=', count($userIds))
        ->get()
        ->first(function ($conversation) use ($userIds) {
            // Ensure it's an exact match (no extra participants)
            return $conversation->participants->count() === count($userIds);
        });

        if ($conv) {
            return response()->json($conv->load('participants'));
        }

        // Create new conversation and attach users
        $conversation = Conversation::create([]);
        $conversation->participants()->attach($validated['user_ids']);
        return response()->json($conversation->load('participants'));
    }

    // PUT/PATCH /api/conversations/{id}
    public function update(Request $request, $id)
    {
        $conversation = Conversation::findOrFail($id);
        $conversation->update($request->only([]));
        return $conversation->load('participants');
    }

    // DELETE /api/conversations/{id}
    public function destroy($id)
    {
        $conversation = Conversation::findOrFail($id);
        $conversation->participants()->detach();
        $conversation->delete();
        return response()->json(['message' => 'Conversation deleted.']);
    }

    // POST /api/conversations/{id}/mark-read
    public function markAsRead(Request $request, $id)
    {
        $conversation = Conversation::findOrFail($id);
        $userId = $request->user()->id;

        // Verify user is a participant
        if (!$conversation->participants()->where('user_id', $userId)->exists()) {
            return response()->json(['message' => 'Not authorized'], 403);
        }

        // Update the last_read_at timestamp
        $conversation->participants()->updateExistingPivot($userId, [
            'last_read_at' => now()
        ]);

        return response()->json(['message' => 'Conversation marked as read']);
    }
}
