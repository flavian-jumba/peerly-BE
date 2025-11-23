<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    // GET /api/groups
    public function index(Request $request)
    {
        $currentUserId = $request->user()->id;
        
        $groups = Group::with(['users', 'owner'])->paginate(10);

        // Add unread count for each group
        $groups->getCollection()->transform(function ($group) use ($currentUserId) {
            // Check if user is a member
            $pivot = $group->users()->where('user_id', $currentUserId)->first();
            
            $unreadCount = 0;
            if ($pivot) {
                $lastReadAt = $pivot->pivot->last_read_at;

                // Count messages after last_read_at
                $unreadCount = $group->messages()
                    ->when($lastReadAt, function ($q) use ($lastReadAt) {
                        return $q->where('created_at', '>', $lastReadAt);
                    }, function ($q) {
                        // If never read, count all messages
                        return $q;
                    })
                    ->count();
            }

            $group->unread_count = $unreadCount;
            return $group;
        });

        return $groups;
    }

    // GET /api/groups/{id}
    public function show($id)
    {
        return Group::with(['users', 'owner'])->findOrFail($id);
    }

    // POST /api/groups
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'bio' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:255',
        ]);

        $group = Group::create([
            ...$validated,
            'owner_id' => $request->user()->id,
        ]);

        // Auto-add the creator as a member
        $group->users()->attach($request->user()->id);

        // Optionally attach additional users if provided
        if ($request->has('user_ids')) {
            $group->users()->attach($request->input('user_ids'));
        }

        return $group->load(['users', 'owner']);
    }

    // PUT/PATCH /api/groups/{id}
    public function update(Request $request, $id)
    {
        $group = Group::findOrFail($id);

        // Only owner can update group
        if ($group->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the group owner can update this group'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'bio' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:255',
        ]);

        $group->update($validated);

        return $group->load(['users', 'owner']);
    }

    // DELETE /api/groups/{id}
    public function destroy(Request $request, $id)
    {
        $group = Group::findOrFail($id);

        // Only owner can delete group
        if ($group->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the group owner can delete this group'], 403);
        }

        // Messages will cascade delete automatically
        $group->users()->detach();
        $group->delete();

        return response()->json(['message' => 'Group deleted successfully']);
    }

    // POST /api/groups/{id}/join
    public function join(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();

        // Check if already a member
        if ($group->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Already a member of this group.'], 400);
        }

        $group->users()->attach($user->id);

        return response()->json([
            'message' => 'Successfully joined the group.',
            'group' => $group->load('users')
        ]);
    }

    // POST /api/groups/{id}/leave
    public function leave(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();

        // If the user is the owner, delete the entire group
        if ($group->owner_id === $user->id) {
            $group->users()->detach();
            $group->delete();
            
            return response()->json([
                'message' => 'Group deleted successfully (owner left).',
                'deleted' => true
            ]);
        }

        // Otherwise just remove the user from the group
        $group->users()->detach($user->id);

        return response()->json([
            'message' => 'Successfully left the group.',
            'group' => $group->load('users')
        ]);
    }

    // POST /api/groups/{id}/mark-read
    public function markAsRead(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $userId = $request->user()->id;

        // Verify user is a member
        if (!$group->users()->where('user_id', $userId)->exists()) {
            return response()->json(['message' => 'Not authorized'], 403);
        }

        // Update the last_read_at timestamp
        $group->users()->updateExistingPivot($userId, [
            'last_read_at' => now()
        ]);

        return response()->json(['message' => 'Group marked as read']);
    }
}
