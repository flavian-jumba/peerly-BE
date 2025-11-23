<?php 


namespace App\Http\Controllers\Api\V1;

use App\Events\MessageSent;
use App\Events\NotificationCreated;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notifications;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    // GET /api/messages
    public function index(Request $request)
    {
        // Optional: filter by conversation_id for thread
        $query = Message::with(['conversation', 'user']);
        if ($request->has('conversation_id')) {
            $query->where('conversation_id', $request->conversation_id);
        }
        return $query->orderBy('created_at', 'asc')->paginate(50);
    }

    // GET /api/messages/{id}
    public function show($id)
    {
        return Message::with(['conversation', 'user'])->findOrFail($id);
    }

    // POST /api/messages
    public function store(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string|max:5000',
        ]);
        
        // Use authenticated user as sender
        $validated['user_id'] = $request->user()->id;
        
        $message = Message::create($validated);

        // Broadcast the message to all participants in the conversation
        broadcast(new MessageSent($message))->toOthers();

        // Create notifications for all conversation participants except the sender
        $conversation = Conversation::with('participants')->find($validated['conversation_id']);
        $sender = $message->user;
        
        foreach ($conversation->participants as $participant) {
            // Don't notify the sender
            if ($participant->id !== $validated['user_id']) {
                $notification = Notifications::create([
                    'user_id' => $participant->id,
                    'type' => 'new_message',
                    'title' => 'New Message',
                    'message' => $sender->name . ' sent you a message: ' . substr($validated['message'], 0, 50) . (strlen($validated['message']) > 50 ? '...' : ''),
                    'read' => false,
                ]);

                // Broadcast the notification in real-time
                broadcast(new NotificationCreated($notification));
            }
        }

        return $message->load(['conversation', 'user']);
    }

    // PUT/PATCH /api/messages/{id}
    public function update(Request $request, $id)
    {
        $message = Message::findOrFail($id);
        // Only allow editing user's own message, add auth as needed
        $message->update($request->only(['message']));
        return $message->load(['conversation', 'user']);
    }

    // DELETE /api/messages/{id}
    public function destroy($id)
    {
        $message = Message::findOrFail($id);
        $message->delete();
        return response()->json(['message' => 'Message deleted.']);
    }
}
