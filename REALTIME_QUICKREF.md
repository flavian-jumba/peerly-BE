# Real-Time Features - Quick Reference

## 🔑 Pusher Credentials
```
App ID: 2078673
Key: 2c3fa5876ebc0bd6cb6c
Secret: a40578d51c1bcf679522
Cluster: mt1
```

## 📡 Channels

| Channel | Purpose | Authorization |
|---------|---------|---------------|
| `private-conversation.{id}` | Real-time messages | User must be conversation participant |
| `private-user.{id}` | User notifications | User ID must match |

## 🎯 Events

### message.sent
**Channel:** `private-conversation.{conversationId}`
```javascript
{
  id: 456,
  conversation_id: 123,
  user_id: 789,
  message: "Hello!",
  created_at: "2025-11-17T12:34:56.000000Z",
  user: {
    id: 789,
    name: "John Doe",
    email: "john@example.com"
  }
}
```

### notification.created
**Channel:** `private-user.{userId}`
```javascript
{
  id: 101,
  type: "new_message",
  title: "New Message",
  message: "John Doe sent you a message: Hello!",
  read: false,
  created_at: "2025-11-17T12:34:56.000000Z"
}
```

## 🛠️ API Endpoints

### Messages
- `POST /api/v1/messages` - Send message (triggers broadcast)
- `GET /api/v1/messages?conversation_id={id}` - Get messages

### Notifications
- `GET /api/v1/notifications` - List all notifications
- `GET /api/v1/notifications/unread-count` - Get unread count
- `PUT /api/v1/notifications/{id}/mark-read` - Mark as read
- `PUT /api/v1/notifications/mark-all-read` - Mark all as read
- `DELETE /api/v1/notifications/{id}` - Delete notification

## 💻 Frontend Quick Start

```javascript
// 1. Install
npm install pusher-js

// 2. Initialize
import Pusher from 'pusher-js';

const pusher = new Pusher('2c3fa5876ebc0bd6cb6c', {
  cluster: 'mt1',
  authEndpoint: 'http://localhost:8000/broadcasting/auth',
  auth: {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  }
});

// 3. Subscribe & Listen
// Messages
const msgChannel = pusher.subscribe('private-conversation.123');
msgChannel.bind('message.sent', (data) => {
  console.log('New message:', data);
});

// Notifications
const notifChannel = pusher.subscribe('private-user.789');
notifChannel.bind('notification.created', (data) => {
  console.log('New notification:', data);
});

// 4. Send Message
await axios.post('/api/v1/messages', {
  conversation_id: 123,
  user_id: 789,
  message: 'Hello!'
}, {
  headers: { 'Authorization': `Bearer ${token}` }
});
// → Automatically broadcasts to all participants
// → Creates notifications for recipients
```

## 🧪 Quick Test

```bash
# Test message endpoint
curl -X POST http://localhost:8000/api/v1/messages \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"conversation_id": 1, "user_id": 1, "message": "Test"}'

# Watch events at: https://dashboard.pusher.com (Debug Console)
```

## 🔍 Troubleshooting

| Issue | Solution |
|-------|----------|
| Can't subscribe to channel | Check Sanctum token in auth headers |
| Events not received | Verify Pusher credentials, check Debug Console |
| 403 on subscription | User doesn't have access to that channel |
| Connection fails | Check firewall, verify cluster is 'mt1' |

## 📖 Full Documentation
- See `REALTIME_INTEGRATION.md` for complete guide
- See `REALTIME_SUMMARY.md` for implementation details
