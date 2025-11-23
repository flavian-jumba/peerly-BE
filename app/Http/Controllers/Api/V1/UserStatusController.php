<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserStatusController extends Controller
{
    /**
     * Update user's online status
     */
    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:online,offline,away',
        ]);

        $user = $request->user();

        // Store status in cache (expires after 5 minutes of inactivity)
        $cacheKey = "user_status_{$user->id}";
        Cache::put($cacheKey, [
            'user_id' => $user->id,
            'name' => $user->name,
            'status' => $validated['status'],
            'last_seen' => now()->toISOString(),
        ], now()->addMinutes(5));

        // Broadcast status change
        broadcast(new UserStatusChanged($user->id, $user->name, $validated['status']));

        return response()->json([
            'message' => 'Status updated successfully',
            'status' => $validated['status'],
        ]);
    }

    /**
     * Get online users
     */
    public function getOnlineUsers()
    {
        $onlineUsers = [];
        $allUsers = User::all();

        foreach ($allUsers as $user) {
            $cacheKey = "user_status_{$user->id}";
            $status = Cache::get($cacheKey);

            if ($status) {
                $onlineUsers[] = $status;
            }
        }

        return response()->json([
            'online_users' => $onlineUsers,
            'count' => count($onlineUsers),
        ]);
    }

    /**
     * Get specific user's status
     */
    public function getUserStatus($userId)
    {
        $cacheKey = "user_status_{$userId}";
        $status = Cache::get($cacheKey);

        if ($status) {
            return response()->json($status);
        }

        $user = User::findOrFail($userId);
        
        return response()->json([
            'user_id' => $user->id,
            'name' => $user->name,
            'status' => 'offline',
            'last_seen' => null,
        ]);
    }

    /**
     * Heartbeat endpoint - keeps user online
     */
    public function heartbeat(Request $request)
    {
        $user = $request->user();
        
        // Also update profile online_status
        if ($user->profile) {
            $user->profile->update(['online_status' => true]);
        }
        
        $cacheKey = "user_status_{$user->id}";

        // Refresh the cache TTL
        Cache::put($cacheKey, [
            'user_id' => $user->id,
            'name' => $user->name,
            'status' => 'online',
            'last_seen' => now()->toISOString(),
        ], now()->addMinutes(5));

        return response()->json(['message' => 'Heartbeat received']);
    }
}
