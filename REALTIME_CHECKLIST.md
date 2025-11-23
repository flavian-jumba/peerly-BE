# ✅ Real-Time Implementation Checklist

## Backend Setup

- [x] **Pusher Package Installed**
  - `pusher/pusher-php-server` is installed via Composer

- [x] **Environment Configuration**
  - `.env` has Pusher credentials (App ID, Key, Secret, Cluster)
  - `BROADCAST_CONNECTION=pusher`
  - `BROADCAST_DRIVER=pusher`

- [x] **Broadcasting Configuration**
  - `config/broadcasting.php` created with Pusher settings
  - BroadcastServiceProvider created and registered
  - `routes/channels.php` created with channel authorization

- [x] **Event Classes Created**
  - `app/Events/MessageSent.php` - Broadcasts messages
  - `app/Events/NotificationCreated.php` - Broadcasts notifications

- [x] **Controllers Updated**
  - MessageController broadcasts events on new message
  - MessageController creates notifications for participants
  - NotificationController has helper methods (unread count, mark as read)

- [x] **API Routes Configured**
  - `/broadcasting/auth` endpoint available
  - Notification endpoints available
  - Message endpoints available

- [x] **Cache Cleared**
  - Configuration cache cleared
  - Application cache cleared

## Testing Checklist

### Backend Testing

- [ ] **Start Laravel Server**
  ```bash
  cd /home/infinity/coding/laravel/Peerly/peerlyapp-API
  php artisan serve
  ```

- [ ] **Verify Routes**
  ```bash
  php artisan route:list --path=broadcasting
  php artisan route:list --path=notifications
  php artisan route:list --path=messages
  ```

- [ ] **Check Pusher Dashboard**
  - Visit https://dashboard.pusher.com
  - Navigate to your app
  - Open "Debug Console"
  - Keep it open for monitoring

- [ ] **Test Message Creation**
  ```bash
  # First, get an auth token (login)
  curl -X POST http://localhost:8000/api/login \
    -H "Content-Type: application/json" \
    -d '{"email":"your-email@example.com","password":"your-password"}'

  # Then send a message (replace YOUR_TOKEN, conversation_id, user_id)
  curl -X POST http://localhost:8000/api/v1/messages \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
      "conversation_id": 1,
      "user_id": 1,
      "message": "Test real-time message"
    }'
  
  # Check Pusher Debug Console - you should see:
  # - Event on channel: private-conversation.1
  # - Event name: message.sent
  # - Multiple events on private-user.{id} channels
  # - Event name: notification.created
  ```

- [ ] **Test Notification Endpoints**
  ```bash
  # Get unread count
  curl -X GET http://localhost:8000/api/v1/notifications/unread-count \
    -H "Authorization: Bearer YOUR_TOKEN"

  # Get all notifications
  curl -X GET http://localhost:8000/api/v1/notifications \
    -H "Authorization: Bearer YOUR_TOKEN"

  # Mark notification as read
  curl -X PUT http://localhost:8000/api/v1/notifications/1/mark-read \
    -H "Authorization: Bearer YOUR_TOKEN"
  ```

### Frontend Testing

- [ ] **Install Pusher JS**
  ```bash
  npm install pusher-js
  # or
  yarn add pusher-js
  ```

- [ ] **Initialize Pusher Client**
  ```javascript
  import Pusher from 'pusher-js';

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

  console.log('Pusher initialized:', pusher.connection.state);
  ```

- [ ] **Test Connection**
  ```javascript
  pusher.connection.bind('connected', () => {
    console.log('✅ Connected to Pusher!');
  });

  pusher.connection.bind('error', (err) => {
    console.error('❌ Pusher connection error:', err);
  });
  ```

- [ ] **Subscribe to Conversation Channel**
  ```javascript
  const conversationId = 1; // Your conversation ID
  const channel = pusher.subscribe(`private-conversation.${conversationId}`);

  channel.bind('pusher:subscription_succeeded', () => {
    console.log('✅ Subscribed to conversation channel!');
  });

  channel.bind('pusher:subscription_error', (error) => {
    console.error('❌ Subscription error:', error);
  });

  channel.bind('message.sent', (data) => {
    console.log('📨 New message received:', data);
    // Update your UI here
  });
  ```

- [ ] **Subscribe to User Notification Channel**
  ```javascript
  const userId = 1; // Current user's ID
  const notifChannel = pusher.subscribe(`private-user.${userId}`);

  notifChannel.bind('pusher:subscription_succeeded', () => {
    console.log('✅ Subscribed to notification channel!');
  });

  notifChannel.bind('notification.created', (data) => {
    console.log('🔔 New notification:', data);
    // Show notification toast/alert
  });
  ```

- [ ] **Send Test Message**
  ```javascript
  // Send message via API
  const response = await fetch('http://localhost:8000/api/v1/messages', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${yourToken}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({
      conversation_id: 1,
      user_id: 1,
      message: 'Hello from frontend!'
    })
  });

  // Other participants should receive the message in real-time
  // Check console for "New message received" log
  ```

## Troubleshooting

### Issue: Subscription fails with 403
- **Cause**: User not authorized for channel
- **Solution**: 
  - Check if user is a participant in the conversation
  - Verify Sanctum token is valid
  - Check `routes/channels.php` authorization logic

### Issue: No events received
- **Cause**: Broadcasting not configured properly
- **Solution**:
  - Verify `BROADCAST_CONNECTION=pusher` in `.env`
  - Check Pusher credentials are correct
  - Verify events are visible in Pusher Debug Console
  - Ensure you're listening to correct channel and event names

### Issue: Authentication fails
- **Cause**: Sanctum token issues
- **Solution**:
  - Verify token is valid and not expired
  - Check `SANCTUM_STATEFUL_DOMAINS` includes your frontend domain
  - Ensure CORS is properly configured

### Issue: Events not showing in Pusher Dashboard
- **Cause**: Broadcast not triggering
- **Solution**:
  - Check Laravel logs: `tail -f storage/logs/laravel.log`
  - Verify Pusher credentials in `.env`
  - Test Pusher connection manually
  - Ensure `broadcast()` function is being called

## Production Checklist

- [ ] **Environment Variables**
  - Update `APP_URL` and `FRONTEND_URL` for production
  - Use production Pusher app (not the same as development)
  - Set `BROADCAST_CONNECTION=pusher`

- [ ] **Security**
  - Enable HTTPS (required for secure WebSocket)
  - Configure CORS for production frontend domain
  - Update `SANCTUM_STATEFUL_DOMAINS`
  - Never expose `PUSHER_APP_SECRET` to frontend

- [ ] **Performance**
  - Enable queue for broadcasting: `php artisan queue:work`
  - Use Redis for better queue performance
  - Monitor Pusher message limits

- [ ] **Monitoring**
  - Set up Pusher webhook for monitoring
  - Log broadcast failures
  - Monitor channel subscription counts

## Documentation References

- **Full Integration Guide**: `REALTIME_INTEGRATION.md`
- **Implementation Summary**: `REALTIME_SUMMARY.md`
- **Quick Reference**: `REALTIME_QUICKREF.md`
- **Architecture Diagrams**: `REALTIME_ARCHITECTURE.md`

## Support

If you encounter issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check Pusher Debug Console
3. Check browser console for JavaScript errors
4. Verify all environment variables are set correctly
5. Test with simple curl commands first

## Next Steps

Once everything is working:
1. Implement typing indicators
2. Add presence channels for online status
3. Add read receipts
4. Queue broadcasts for better performance
5. Add webhook handlers for Pusher events

---

**Status**: ✅ All backend components are implemented and ready for testing!
