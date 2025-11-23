# Real-Time Architecture Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         REAL-TIME MESSAGE FLOW                           │
└─────────────────────────────────────────────────────────────────────────┘

Frontend (User A)                 Laravel API                 Frontend (User B)
     │                                 │                             │
     │  1. POST /api/v1/messages      │                             │
     │  {conversation_id, message}    │                             │
     ├────────────────────────────────>│                             │
     │                                 │                             │
     │                          2. Save to DB                        │
     │                                 │                             │
     │                    3. Broadcast MessageSent                   │
     │                      to conversation channel                  │
     │                                 │                             │
     │<────────────────────────────────┼────────────────────────────>│
     │  4. Receive via Pusher          │   4. Receive via Pusher     │
     │  private-conversation.{id}      │   private-conversation.{id} │
     │  Event: message.sent            │   Event: message.sent       │
     │                                 │                             │
     │                      5. Create Notifications                  │
     │                         for participants                      │
     │                                 │                             │
     │                  6. Broadcast NotificationCreated             │
     │                        to user channels                       │
     │                                 │                             │
     │                                 ┼────────────────────────────>│
     │                                 │   7. Receive via Pusher     │
     │                                 │   private-user.{userId}     │
     │                                 │   Event: notification.created│
     │                                 │                             │
     │  8. Update UI with message      │  8. Update UI with message  │
     │     & notification              │     & notification          │
     │                                 │                             │


┌─────────────────────────────────────────────────────────────────────────┐
│                      CHANNEL AUTHORIZATION FLOW                          │
└─────────────────────────────────────────────────────────────────────────┘

Frontend                           Laravel API
    │                                   │
    │  1. Subscribe to channel          │
    │  pusher.subscribe(               │
    │    'private-conversation.123'     │
    │  )                                │
    ├──────────────────────────────────>│
    │                                   │
    │  2. Pusher SDK auto-calls         │
    │  POST /broadcasting/auth          │
    │  with channel name & token        │
    ├──────────────────────────────────>│
    │                                   │
    │                         3. Verify Sanctum token
    │                            & check permissions
    │                            (routes/channels.php)
    │                                   │
    │                    4a. If authorized: Return signature
    │<──────────────────────────────────┤
    │  Subscription successful          │
    │                                   │
    │                    4b. If unauthorized: Return 403
    │<──────────────────────────────────┤
    │  Subscription failed              │
    │                                   │


┌─────────────────────────────────────────────────────────────────────────┐
│                         DATA STRUCTURE                                   │
└─────────────────────────────────────────────────────────────────────────┘

Message Event (message.sent):
{
  "id": 456,
  "conversation_id": 123,
  "user_id": 789,
  "message": "Hello, how are you?",
  "created_at": "2025-11-17T12:34:56.000000Z",
  "user": {
    "id": 789,
    "name": "John Doe",
    "email": "john@example.com"
  }
}

Notification Event (notification.created):
{
  "id": 101,
  "type": "new_message",
  "title": "New Message",
  "message": "John Doe sent you a message: Hello, how are you?",
  "read": false,
  "created_at": "2025-11-17T12:34:56.000000Z"
}


┌─────────────────────────────────────────────────────────────────────────┐
│                      SECURITY ARCHITECTURE                               │
└─────────────────────────────────────────────────────────────────────────┘

Layer 1: API Authentication
    ↓
  Sanctum Token Required
    ↓
Layer 2: Channel Authorization
    ↓
  Private Channel Check (routes/channels.php)
    │
    ├─> private-conversation.{id}
    │   └─> User must be participant in conversation
    │
    └─> private-user.{id}
        └─> User ID must match authenticated user
    ↓
Layer 3: Pusher Secure Connection
    ↓
  TLS/SSL encrypted WebSocket
    ↓
Real-time data delivery


┌─────────────────────────────────────────────────────────────────────────┐
│                    COMPONENT INTERACTION MAP                             │
└─────────────────────────────────────────────────────────────────────────┘

MessageController::store()
       │
       ├─> Message::create()          [Database]
       │
       ├─> broadcast(MessageSent)     [Pusher]
       │        │
       │        └─> private-conversation.{id}
       │            └─> All participants receive
       │
       └─> foreach participants
                │
                ├─> Notification::create()     [Database]
                │
                └─> broadcast(NotificationCreated) [Pusher]
                         │
                         └─> private-user.{id}
                             └─> Individual user receives


┌─────────────────────────────────────────────────────────────────────────┐
│                         FILE STRUCTURE                                   │
└─────────────────────────────────────────────────────────────────────────┘

app/
├── Events/
│   ├── MessageSent.php              ─> Broadcasts new messages
│   └── NotificationCreated.php      ─> Broadcasts notifications
├── Http/Controllers/Api/V1/
│   ├── MessageController.php        ─> Handles message CRUD + broadcasting
│   └── NotificationController.php   ─> Handles notifications
├── Models/
│   ├── Message.php                  ─> Message model
│   ├── Conversation.php             ─> Conversation model
│   ├── Notifications.php            ─> Notification model
│   └── User.php                     ─> User with relationships
└── Providers/
    └── BroadcastServiceProvider.php ─> Registers channel routes

config/
└── broadcasting.php                 ─> Pusher configuration

routes/
├── api.php                          ─> API routes
└── channels.php                     ─> Channel authorization rules
