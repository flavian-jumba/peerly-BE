# Real-Time Messaging & Notifications - Frontend Integration Guide

## Overview
Your Laravel API now supports real-time messaging and notifications using Pusher. This guide explains how to integrate with your frontend application.

## Pusher Configuration

### API Credentials
```javascript
const pusherConfig = {
  key: '2c3fa5876ebc0bd6cb6c',
  cluster: 'mt1',
  authEndpoint: 'http://localhost:8000/broadcasting/auth',
  auth: {
    headers: {
      'Authorization': 'Bearer YOUR_SANCTUM_TOKEN',
      'Accept': 'application/json',
    }
  }
};
```

## Frontend Setup (JavaScript/React Example)

### 1. Install Pusher JS SDK
```bash
npm install pusher-js
# or
yarn add pusher-js
```

### 2. Initialize Pusher Client
```javascript
import Pusher from 'pusher-js';

// Initialize Pusher
const pusher = new Pusher('2c3fa5876ebc0bd6cb6c', {
  cluster: 'mt1',
  authEndpoint: 'http://localhost:8000/broadcasting/auth',
  auth: {
    headers: {
      'Authorization': `Bearer ${yourSanctumToken}`,
      'Accept': 'application/json',
    }
  }
});
```

## Real-Time Events

### 1. Listen for New Messages

Subscribe to conversation channel to receive real-time messages:

```javascript
// Subscribe to a specific conversation
const conversationId = 123; // Your conversation ID
const channel = pusher.subscribe(`private-conversation.${conversationId}`);

// Listen for new messages
channel.bind('message.sent', (data) => {
  console.log('New message received:', data);
  
  // Data structure:
  // {
  //   id: 456,
  //   conversation_id: 123,
  //   user_id: 789,
  //   message: "Hello, how are you?",
  //   created_at: "2025-11-17T12:34:56.000000Z",
  //   user: {
  //     id: 789,
  //     name: "John Doe",
  //     email: "john@example.com"
  //   }
  // }
  
  // Update your UI with the new message
  addMessageToConversation(data);
});
```

### 2. Listen for Notifications

Subscribe to user channel to receive notifications:

```javascript
// Subscribe to user's private notification channel
const userId = 789; // Current user's ID
const notificationChannel = pusher.subscribe(`private-user.${userId}`);

// Listen for new notifications
notificationChannel.bind('notification.created', (data) => {
  console.log('New notification:', data);
  
  // Data structure:
  // {
  //   id: 101,
  //   type: "new_message",
  //   title: "New Message",
  //   message: "John Doe sent you a message: Hello, how are you?",
  //   read: false,
  //   created_at: "2025-11-17T12:34:56.000000Z"
  // }
  
  // Show notification in UI
  displayNotification(data);
  
  // Update unread count
  incrementUnreadCount();
});
```

## API Endpoints

### Messages

#### Send a Message
```javascript
POST /api/v1/messages

// Request body
{
  "conversation_id": 123,
  "user_id": 789,
  "message": "Hello, how are you?"
}

// Response: Message object + Real-time broadcast to conversation participants
```

#### Get Conversation Messages
```javascript
GET /api/v1/messages?conversation_id=123

// Returns paginated messages for the conversation
```

### Notifications

#### Get All Notifications
```javascript
GET /api/v1/notifications

// Returns paginated list of user's notifications
```

#### Get Unread Count
```javascript
GET /api/v1/notifications/unread-count

// Response: { "unread_count": 5 }
```

#### Mark Notification as Read
```javascript
PUT /api/v1/notifications/{id}/mark-read

// Marks single notification as read
```

#### Mark All as Read
```javascript
PUT /api/v1/notifications/mark-all-read

// Marks all user's notifications as read
```

#### Delete Notification
```javascript
DELETE /api/v1/notifications/{id}
```

## Complete React Example

```javascript
import React, { useEffect, useState } from 'react';
import Pusher from 'pusher-js';
import axios from 'axios';

function ChatComponent({ conversationId, userId, token }) {
  const [messages, setMessages] = useState([]);
  const [pusher, setPusher] = useState(null);

  useEffect(() => {
    // Initialize Pusher
    const pusherInstance = new Pusher('2c3fa5876ebc0bd6cb6c', {
      cluster: 'mt1',
      authEndpoint: 'http://localhost:8000/broadcasting/auth',
      auth: {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
        }
      }
    });
    setPusher(pusherInstance);

    // Subscribe to conversation channel
    const conversationChannel = pusherInstance.subscribe(
      `private-conversation.${conversationId}`
    );

    // Listen for new messages
    conversationChannel.bind('message.sent', (data) => {
      setMessages(prevMessages => [...prevMessages, data]);
    });

    // Subscribe to notification channel
    const notificationChannel = pusherInstance.subscribe(
      `private-user.${userId}`
    );

    // Listen for notifications
    notificationChannel.bind('notification.created', (data) => {
      // Show toast notification or update notification bell
      showNotification(data);
    });

    // Cleanup on unmount
    return () => {
      conversationChannel.unbind_all();
      notificationChannel.unbind_all();
      pusherInstance.unsubscribe(`private-conversation.${conversationId}`);
      pusherInstance.unsubscribe(`private-user.${userId}`);
      pusherInstance.disconnect();
    };
  }, [conversationId, userId, token]);

  const sendMessage = async (messageText) => {
    try {
      const response = await axios.post(
        'http://localhost:8000/api/v1/messages',
        {
          conversation_id: conversationId,
          user_id: userId,
          message: messageText
        },
        {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
          }
        }
      );
      
      // Message is automatically broadcast to other participants
      // Add to local state for sender
      setMessages(prevMessages => [...prevMessages, response.data]);
    } catch (error) {
      console.error('Error sending message:', error);
    }
  };

  return (
    <div>
      {/* Your chat UI */}
    </div>
  );
}

export default ChatComponent;
```

## Channel Authorization

The API automatically handles channel authorization. When your frontend tries to subscribe to a private channel, Pusher will make a request to:

```
POST /broadcasting/auth
```

This endpoint checks if:
- User is authenticated (via Sanctum token)
- User has permission to access the channel
  - For `private-conversation.{id}`: User must be a participant
  - For `private-user.{id}`: User ID must match

Make sure to always include the Sanctum token in your Pusher auth headers.

## Event Flow

### When a message is sent:

1. Frontend calls `POST /api/v1/messages`
2. Backend creates message in database
3. Backend broadcasts `MessageSent` event to `private-conversation.{id}`
4. Backend creates notifications for all participants (except sender)
5. Backend broadcasts `NotificationCreated` event to each participant's `private-user.{id}` channel
6. All connected clients receive updates in real-time

## Testing

### Test with Pusher Debug Console
1. Visit https://dashboard.pusher.com
2. Select your app
3. Go to "Debug Console"
4. Send a message via API
5. Watch events appear in real-time

## Security Notes

- All channels are **private** - users can only subscribe to channels they have access to
- Channel authorization is handled server-side
- Always use HTTPS in production
- Never expose your Pusher secret key to the frontend
- Use Sanctum tokens for API authentication

## Troubleshooting

### Channel subscription fails
- Verify Sanctum token is valid and included in auth headers
- Check if user is a participant in the conversation
- Verify Pusher credentials in `.env`

### Events not received
- Check Pusher Debug Console for event delivery
- Verify correct channel name and event name
- Ensure pusher connection is established before subscribing

### CORS issues
- Ensure CORS is properly configured in Laravel
- Check that `SANCTUM_STATEFUL_DOMAINS` includes your frontend domain
