<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private channel for individual user notifications
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for user status updates
Broadcast::channel('user-status', function ($user) {
    return $user ? true : false;
});

// Private channel for conversation messages
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    // Check if the user is a participant in the conversation
    return $user->conversations()->where('conversations.id', $conversationId)->exists();
});

// Presence channel - tracks online users globally
Broadcast::channel('online-users', function ($user) {
    if ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
});

// Presence channel for specific conversation - tracks who's viewing the conversation
Broadcast::channel('conversation-presence.{conversationId}', function ($user, $conversationId) {
    // Check if the user is a participant in the conversation
    if ($user->conversations()->where('conversations.id', $conversationId)->exists()) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
});
