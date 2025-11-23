# User Presence & Online Status - Implementation Guide

## Overview

The presence system tracks user online/offline status using **two approaches**:

1. **Pusher Presence Channels** - Automatic tracking (recommended)
2. **Manual Status API** - Explicit status updates with heartbeat

## Approach 1: Pusher Presence Channels (Recommended)

### Backend Setup (Already Configured)

Presence channels are defined in `routes/channels.php`:
- `presence-online-users` - Global online users
- `presence-conversation-presence.{id}` - Who's viewing a specific conversation

### Frontend Implementation

#### 1. Subscribe to Global Presence Channel

```javascript
// src/services/presence.js
import pusher from './pusher';

class PresenceService {
  constructor() {
    this.onlineUsers = new Map();
    this.presenceChannel = null;
  }

  // Subscribe to global presence channel
  subscribeToGlobalPresence(currentUser) {
    this.presenceChannel = pusher.subscribe('presence-online-users');

    // Member added (user comes online)
    this.presenceChannel.bind('pusher:subscription_succeeded', (members) => {
      console.log('Online users:', members.count);
      members.each((member) => {
        this.onlineUsers.set(member.id, member.info);
      });
    });

    // Member added
    this.presenceChannel.bind('pusher:member_added', (member) => {
      console.log('User came online:', member.info.name);
      this.onlineUsers.set(member.id, member.info);
    });

    // Member removed (user goes offline)
    this.presenceChannel.bind('pusher:member_removed', (member) => {
      console.log('User went offline:', member.info.name);
      this.onlineUsers.delete(member.id);
    });
  }

  // Get all online users
  getOnlineUsers() {
    return Array.from(this.onlineUsers.values());
  }

  // Check if specific user is online
  isUserOnline(userId) {
    return this.onlineUsers.has(userId);
  }

  // Unsubscribe
  unsubscribe() {
    if (this.presenceChannel) {
      pusher.unsubscribe('presence-online-users');
      this.onlineUsers.clear();
    }
  }
}

export default new PresenceService();
```

#### 2. Subscribe to Conversation Presence

```javascript
// Track who's viewing a specific conversation
class ConversationPresence {
  subscribeToConversation(conversationId) {
    const channel = pusher.subscribe(`presence-conversation-presence.${conversationId}`);

    channel.bind('pusher:subscription_succeeded', (members) => {
      console.log(`${members.count} users viewing this conversation`);
      members.each((member) => {
        console.log(`${member.info.name} is viewing`);
      });
    });

    channel.bind('pusher:member_added', (member) => {
      console.log(`${member.info.name} started viewing`);
      // Show "User is viewing this conversation" indicator
    });

    channel.bind('pusher:member_removed', (member) => {
      console.log(`${member.info.name} stopped viewing`);
      // Hide viewing indicator
    });

    return channel;
  }
}
```

#### 3. React Hook for Presence

```javascript
// src/hooks/usePresence.js
import { useEffect, useState } from 'react';
import presenceService from '../services/presence';
import { authService } from '../services/auth';

export const usePresence = () => {
  const [onlineUsers, setOnlineUsers] = useState([]);
  const currentUser = authService.getUser();

  useEffect(() => {
    if (!currentUser) return;

    // Subscribe to presence
    presenceService.subscribeToGlobalPresence(currentUser);

    // Update online users periodically
    const interval = setInterval(() => {
      setOnlineUsers(presenceService.getOnlineUsers());
    }, 1000);

    return () => {
      clearInterval(interval);
      presenceService.unsubscribe();
    };
  }, [currentUser]);

  const isUserOnline = (userId) => {
    return presenceService.isUserOnline(userId);
  };

  return { onlineUsers, isUserOnline };
};
```

#### 4. Usage in Components

```javascript
// Display online status indicator
import { usePresence } from '../hooks/usePresence';

function UserList({ users }) {
  const { isUserOnline } = usePresence();

  return (
    <div>
      {users.map(user => (
        <div key={user.id} className="user-item">
          <img src={user.avatar} alt={user.name} />
          <span>{user.name}</span>
          
          {/* Online indicator */}
          <span className={isUserOnline(user.id) ? 'online' : 'offline'}>
            {isUserOnline(user.id) ? '🟢 Online' : '⚫ Offline'}
          </span>
        </div>
      ))}
    </div>
  );
}
```

## Approach 2: Manual Status API with Heartbeat

### Backend Setup (Already Configured)

API endpoints available:
- `POST /api/v1/user-status` - Update status
- `GET /api/v1/online-users` - Get all online users
- `GET /api/v1/user-status/{userId}` - Get specific user status
- `POST /api/v1/heartbeat` - Keep user online

### Frontend Implementation

#### 1. Status Service

```javascript
// src/services/userStatus.js
import api from './api';

export const userStatusService = {
  async updateStatus(status) {
    const response = await api.post(
      `${process.env.REACT_APP_API_URL}/user-status`,
      { status } // 'online', 'offline', 'away'
    );
    return response.data;
  },

  async getOnlineUsers() {
    const response = await api.get(
      `${process.env.REACT_APP_API_URL}/online-users`
    );
    return response.data;
  },

  async getUserStatus(userId) {
    const response = await api.get(
      `${process.env.REACT_APP_API_URL}/user-status/${userId}`
    );
    return response.data;
  },

  async sendHeartbeat() {
    await api.post(`${process.env.REACT_APP_API_URL}/heartbeat`);
  },
};
```

#### 2. Auto Heartbeat Hook

```javascript
// src/hooks/useHeartbeat.js
import { useEffect } from 'react';
import { userStatusService } from '../services/userStatus';

export const useHeartbeat = (enabled = true) => {
  useEffect(() => {
    if (!enabled) return;

    // Set initial status to online
    userStatusService.updateStatus('online');

    // Send heartbeat every 2 minutes
    const heartbeatInterval = setInterval(() => {
      userStatusService.sendHeartbeat().catch(console.error);
    }, 120000); // 2 minutes

    // Set to offline when page is about to unload
    const handleBeforeUnload = () => {
      userStatusService.updateStatus('offline');
    };

    window.addEventListener('beforeunload', handleBeforeUnload);

    // Set to away when user is inactive
    let inactivityTimer;
    const resetInactivityTimer = () => {
      clearTimeout(inactivityTimer);
      userStatusService.updateStatus('online');
      
      inactivityTimer = setTimeout(() => {
        userStatusService.updateStatus('away');
      }, 300000); // 5 minutes of inactivity
    };

    window.addEventListener('mousemove', resetInactivityTimer);
    window.addEventListener('keypress', resetInactivityTimer);
    resetInactivityTimer();

    return () => {
      clearInterval(heartbeatInterval);
      clearTimeout(inactivityTimer);
      window.removeEventListener('beforeunload', handleBeforeUnload);
      window.removeEventListener('mousemove', resetInactivityTimer);
      window.removeEventListener('keypress', resetInactivityTimer);
      userStatusService.updateStatus('offline');
    };
  }, [enabled]);
};
```

#### 3. Status Change Listener

```javascript
// Listen for user status changes via Pusher
import pusher from './pusher';

const statusChannel = pusher.subscribe('private-user-status');

statusChannel.bind('user.status.changed', (data) => {
  console.log(`${data.name} is now ${data.status}`);
  // Update UI to reflect status change
  updateUserStatusInUI(data.user_id, data.status, data.last_seen);
});
```

#### 4. Complete App Integration

```javascript
// src/App.js
import { useHeartbeat } from './hooks/useHeartbeat';
import { usePresence } from './hooks/usePresence';
import { authService } from './services/auth';

function App() {
  const isAuthenticated = authService.getToken() !== null;
  
  // Enable heartbeat if using manual status approach
  useHeartbeat(isAuthenticated);
  
  // OR use presence channels (recommended)
  const { onlineUsers, isUserOnline } = usePresence();

  return (
    <div className="App">
      {/* Your app content */}
    </div>
  );
}
```

## UI Components

### Online Status Badge

```javascript
// src/components/OnlineStatusBadge.jsx
import React from 'react';
import { usePresence } from '../hooks/usePresence';

export const OnlineStatusBadge = ({ userId, showText = true }) => {
  const { isUserOnline } = usePresence();
  const online = isUserOnline(userId);

  return (
    <span className={`status-badge ${online ? 'online' : 'offline'}`}>
      <span className="status-dot" />
      {showText && <span>{online ? 'Online' : 'Offline'}</span>}
    </span>
  );
};

// CSS
/*
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.status-badge.online .status-dot {
  background-color: #10b981;
}

.status-badge.offline .status-dot {
  background-color: #6b7280;
}
*/
```

### Online Users List

```javascript
// src/components/OnlineUsersList.jsx
import React from 'react';
import { usePresence } from '../hooks/usePresence';

export const OnlineUsersList = () => {
  const { onlineUsers } = usePresence();

  return (
    <div className="online-users-list">
      <h3>Online Users ({onlineUsers.length})</h3>
      {onlineUsers.map(user => (
        <div key={user.id} className="online-user">
          <span className="online-indicator">🟢</span>
          <span>{user.name}</span>
        </div>
      ))}
    </div>
  );
};
```

### Conversation Viewer Indicator

```javascript
// src/components/ConversationViewers.jsx
import React, { useEffect, useState } from 'react';
import pusher from '../services/pusher';

export const ConversationViewers = ({ conversationId }) => {
  const [viewers, setViewers] = useState([]);

  useEffect(() => {
    const channel = pusher.subscribe(`presence-conversation-presence.${conversationId}`);

    channel.bind('pusher:subscription_succeeded', (members) => {
      const viewersList = [];
      members.each((member) => {
        if (member.id !== getCurrentUserId()) {
          viewersList.push(member.info);
        }
      });
      setViewers(viewersList);
    });

    channel.bind('pusher:member_added', (member) => {
      if (member.id !== getCurrentUserId()) {
        setViewers(prev => [...prev, member.info]);
      }
    });

    channel.bind('pusher:member_removed', (member) => {
      setViewers(prev => prev.filter(v => v.id !== member.id));
    });

    return () => {
      pusher.unsubscribe(`presence-conversation-presence.${conversationId}`);
    };
  }, [conversationId]);

  if (viewers.length === 0) return null;

  return (
    <div className="conversation-viewers">
      <span>👁️ {viewers.map(v => v.name).join(', ')} viewing</span>
    </div>
  );
};
```

## Environment Variables

Add to your `.env`:

```env
# Presence/Status Configuration
REACT_APP_ENABLE_PRESENCE=true
REACT_APP_HEARTBEAT_INTERVAL=120000
REACT_APP_AWAY_TIMEOUT=300000
REACT_APP_STATUS_REFRESH_INTERVAL=60000

# Presence channels
REACT_APP_GLOBAL_PRESENCE_CHANNEL=presence-online-users
REACT_APP_CONVERSATION_PRESENCE_PREFIX=presence-conversation-presence
```

## Which Approach to Use?

### Use Pusher Presence Channels if:
✅ You want automatic online/offline detection
✅ You need real-time accuracy
✅ You want to know who's viewing specific conversations
✅ You want less backend load

### Use Manual Status API if:
✅ You need custom status (away, busy, do not disturb)
✅ You want to persist last seen time
✅ You need status history/analytics
✅ You want more control over status logic

**Recommendation**: Use **Presence Channels** for simplicity and real-time accuracy. Add Manual API for additional features like "last seen" timestamps.

## Hybrid Approach (Best of Both)

```javascript
// Combine both approaches
const { onlineUsers, isUserOnline } = usePresence(); // Real-time
const [userStatuses, setUserStatuses] = useState({}); // Last seen info

// Fetch last seen for offline users
useEffect(() => {
  const fetchLastSeen = async () => {
    for (const user of offlineUsers) {
      const status = await userStatusService.getUserStatus(user.id);
      setUserStatuses(prev => ({
        ...prev,
        [user.id]: status
      }));
    }
  };
  fetchLastSeen();
}, [offlineUsers]);
```

Your presence system is now fully implemented! 🎉
