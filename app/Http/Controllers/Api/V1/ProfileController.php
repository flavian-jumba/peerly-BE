<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProfileController extends Controller
{
    // GET /api/profiles
    public function index(Request $request)
    {
        $currentUserId = $request->user()->id;
        
        // Only return profiles of users who are currently online
        $profiles = Profile::with('user')
            ->where('online_status', true)
            ->where('user_id', '!=', $currentUserId) // Exclude current user
            ->paginate(50);

        // Add unread count for each profile AND verify they're actually online via cache
        $profiles->getCollection()->transform(function ($profile) use ($currentUserId) {
            // Verify user is actually online by checking cache heartbeat
            $cacheKey = "user_status_{$profile->user_id}";
            $cachedStatus = Cache::get($cacheKey);
            
            // If cache expired (no heartbeat in last 5 minutes), mark as offline and skip
            if (!$cachedStatus) {
                $profile->update(['online_status' => false]);
                return null; // Will be filtered out
            }
            
            // Find conversation between current user and this profile's user
            $conversation = \App\Models\Conversation::whereHas('participants', function ($q) use ($currentUserId) {
                $q->where('users.id', $currentUserId);
            })
            ->whereHas('participants', function ($q) use ($profile) {
                $q->where('users.id', $profile->user_id);
            })
            ->first();

            $unreadCount = 0;
            if ($conversation) {
                // Get the last_read_at timestamp for current user
                $pivot = $conversation->participants()
                    ->where('user_id', $currentUserId)
                    ->first();
                
                $lastReadAt = $pivot ? $pivot->pivot->last_read_at : null;

                // Count messages sent by the OTHER user after last_read_at
                $unreadCount = $conversation->messages()
                    ->where('user_id', $profile->user_id)
                    ->when($lastReadAt, function ($q) use ($lastReadAt) {
                        return $q->where('created_at', '>', $lastReadAt);
                    }, function ($q) {
                        // If never read, count all messages from other user
                        return $q;
                    })
                    ->count();
            }

            $profile->unread_count = $unreadCount;
            return $profile;
        })->filter(); // Remove null values (offline users)

        return $profiles;
    }

    // GET /api/profiles/{id}
    public function show($id)
    {
        return Profile::with('user')->findOrFail($id);
    }

    // POST /api/profiles
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id|unique:profiles,user_id',
            'prefix' => 'required|string|max:255|unique:profiles,prefix',
            'about' => 'nullable|string|max:500',
            'online_status' => 'boolean',
            'avatar' => 'nullable|string',
        ]);
        return Profile::create($validated);
    }

    // PUT/PATCH /api/profiles/{id}
    public function update(Request $request, $id)
    {
        $profile = Profile::findOrFail($id);
        $profile->update($request->only(['prefix', 'about', 'online_status', 'avatar']));
        return $profile->load('user');
    }

    // DELETE /api/profiles/{id}
    public function destroy($id)
    {
        $profile = Profile::findOrFail($id);
        $profile->delete();
        return response()->json(['message' => 'Profile deleted.']);
    }
}
