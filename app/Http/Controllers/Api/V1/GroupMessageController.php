<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupMessageController extends Controller
{
    /**
     * Display a listing of messages for a group.
     */
    public function index($groupId)
    {
        $group = Group::findOrFail($groupId);

        // Check if user is a member of the group
        if (!$group->users()->where('user_id', Auth::id())->exists()) {
            return response()->json(['message' => 'You are not a member of this group'], 403);
        }

        $messages = $group->messages()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    /**
     * Store a newly created message in storage.
     */
    public function store(Request $request, $groupId)
    {
        $group = Group::findOrFail($groupId);

        // Check if user is a member of the group
        if (!$group->users()->where('user_id', Auth::id())->exists()) {
            return response()->json(['message' => 'You are not a member of this group'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $message = GroupMessage::create([
            'group_id' => $groupId,
            'user_id' => Auth::id(),
            'message' => $validated['message'],
        ]);

        $message->load('user:id,name,email');

        return response()->json($message, 201);
    }

    /**
     * Remove the specified message from storage.
     */
    public function destroy($groupId, $messageId)
    {
        $message = GroupMessage::where('group_id', $groupId)
            ->where('id', $messageId)
            ->firstOrFail();

        // Only the message sender can delete it
        if ($message->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message->delete();

        return response()->json(['message' => 'Message deleted successfully']);
    }
}
