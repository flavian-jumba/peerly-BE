# User Presence Feature - Quick Summary

## ✅ What Was Added

### Backend (Laravel API)

**1. Presence Channels** (`routes/channels.php`)
- `presence-online-users` - Tracks all online users globally
- `presence-conversation-presence.{id}` - Tracks who's viewing specific conversations
- `private-user-status` - Broadcasts status change events

**2. Events**
- `UserStatusChanged` - Broadcasts when user status changes (online/offline/away)

**3. Controllers**
- `UserStatusController` - Manages user online/offline status
  - `POST /api/v1/user-status` - Update status
  - `GET /api/v1/online-users` - Get all online users
  - `GET /api/v1/user-status/{userId}` - Get specific user status
  - `POST /api/v1/heartbeat` - Keep user online (auto-refresh)

**4. Caching**
- User status cached for 5 minutes
- Automatically expires if no heartbeat received

### Frontend Configuration

**Environment Variables Added:**
```env
# Presence channels
REACT_APP_GLOBAL_PRESENCE_CHANNEL=presence-online-users
REACT_APP_CONVERSATION_PRESENCE_PREFIX=presence-conversation-presence
REACT_APP_USER_STATUS_CHANNEL=private-user-status

# Status endpoints
REACT_APP_USER_STATUS_ENDPOINT=${REACT_APP_API_URL}/user-status
REACT_APP_ONLINE_USERS_ENDPOINT=${REACT_APP_API_URL}/online-users
REACT_APP_HEARTBEAT_ENDPOINT=${REACT_APP_API_URL}/heartbeat

# Presence configuration
REACT_APP_ENABLE_PRESENCE=true
REACT_APP_HEARTBEAT_INTERVAL=120000
REACT_APP_AWAY_TIMEOUT=300000
```

## 🎯 Two Implementation Approaches

### Option 1: Pusher Presence Channels (Automatic) ⭐ RECOMMENDED

**How it works:**
- Subscribe to `presence-online-users` channel
- Pusher automatically tracks who's subscribed
- Instantly know when users join/leave
- No manual status updates needed

**Pros:**
✅ Fully automatic
✅ Real-time accurate
✅ Zero backend load
✅ Built-in member tracking

**Use case:**
- Show online/offline badges
- Display "User is viewing" in conversations
- List of active users

### Option 2: Manual Status API (Heartbeat)

**How it works:**
- Frontend sends heartbeat every 2 minutes
- Backend stores status in cache (expires after 5 min)
- Frontend can query who's online
- Can set custom statuses (away, busy, etc.)

**Pros:**
✅ Custom status types
✅ Last seen timestamps
✅ Status persistence
✅ More control

**Use case:**
- "Last seen 5 minutes ago"
- Custom status messages
- Status history/analytics

## 🚀 Frontend Implementation Examples

### Show Online Badge Next to User

```javascript
import { usePresence } from '../hooks/usePresence';

function UserAvatar({ userId, name }) {
  const { isUserOnline } = usePresence();
  
  return (
    <div className="user-avatar">
      <img src={`/avatars/${userId}.jpg`} alt={name} />
      {isUserOnline(userId) && (
        <span className="online-badge">🟢</span>
      )}
    </div>
  );
}
```

### Show Who's Viewing Conversation

```javascript
function ConversationHeader({ conversationId }) {
  const [viewers, setViewers] = useState([]);

  useEffect(() => {
    const channel = pusher.subscribe(
      `presence-conversation-presence.${conversationId}`
    );

    channel.bind('pusher:member_added', (member) => {
      if (member.id !== currentUserId) {
        setViewers(prev => [...prev, member.info]);
      }
    });

    channel.bind('pusher:member_removed', (member) => {
      setViewers(prev => prev.filter(v => v.id !== member.id));
    });

    return () => pusher.unsubscribe(channel.name);
  }, [conversationId]);

  return (
    <div>
      <h2>Conversation</h2>
      {viewers.length > 0 && (
        <p>👁️ {viewers.map(v => v.name).join(', ')} viewing</p>
      )}
    </div>
  );
}
```

### Auto Heartbeat (Keep User Online)

```javascript
// In your main App.js or layout component
import { useHeartbeat } from '../hooks/useHeartbeat';

function App() {
  useHeartbeat(true); // Automatically sends heartbeat

  return <div>{/* Your app */}</div>;
}
```

## 📊 How It Works

```
User Opens App
     ↓
Subscribe to presence-online-users
     ↓
Pusher adds user to channel members
     ↓
All other users receive "member_added" event
     ↓
UI shows user as online
     ↓
User closes tab/app
     ↓
Pusher removes user from channel
     ↓
All other users receive "member_removed" event
     ↓
UI shows user as offline
```

## 🎨 UI/UX Suggestions

**Online Indicators:**
- 🟢 Green dot = Online
- 🟡 Yellow dot = Away
- ⚫ Gray dot = Offline
- 🔴 Red dot = Busy/Do Not Disturb

**Display Options:**
- Badge on avatar
- Text status ("Online", "Last seen 5m ago")
- Tooltip on hover
- List of online users in sidebar
- "Currently viewing" indicator in conversations

**Automatic Behaviors:**
- Set "away" after 5 minutes of inactivity
- Set "offline" when page closes
- Show "last seen" for offline users
- Highlight new messages from online users

## 🔒 Security & Privacy

**What's Shared:**
- User ID
- User name
- Online/offline status

**What's NOT Shared:**
- Email (unless you add it)
- Location
- Device info
- IP address

**Privacy Controls You Can Add:**
- "Show online status" toggle
- "Invisible mode"
- Block list (don't show to blocked users)
- Last seen privacy settings

## 📱 Mobile Considerations

For mobile apps:
- Presence channels work the same
- Use background services for heartbeat
- Handle app backgrounding/foregrounding
- Consider battery impact of WebSocket connections

## 🧪 Testing

```bash
# Test status update
curl -X POST http://localhost:8000/api/v1/user-status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "online"}'

# Test get online users
curl -X GET http://localhost:8000/api/v1/online-users \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test heartbeat
curl -X POST http://localhost:8000/api/v1/heartbeat \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 🎉 Summary

You now have **TWO WAYS** to implement user presence:

1. **Pusher Presence Channels** - Automatic, real-time, recommended
2. **Manual Status API** - More control, custom statuses, last seen

**Best approach**: Use **Presence Channels** for online/offline detection + **Status API** for "last seen" timestamps!

See `USER_PRESENCE_GUIDE.md` for complete implementation details and code examples.
