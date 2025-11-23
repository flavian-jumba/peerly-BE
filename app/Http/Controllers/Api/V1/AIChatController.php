<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AIMessage;
use App\Models\Therapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIChatController extends Controller
{
    /**
     * The system prompt that defines the AI therapist's behavior.
     */
    private const THERAPY_SYSTEM_PROMPT = "You are a compassionate and empathetic AI therapy assistant for a mental health support app called Peerly and your name is Peerly. Your role is to:

1. Listen attentively to users' concerns and stories with empathy and without judgment
2. Provide emotional support and validation for their feelings
3. Offer thoughtful guidance and coping strategies for managing stress, anxiety, and other mental health challenges
4. Ask clarifying questions to better understand their situation
5. Suggest healthy perspectives and behavioral strategies when appropriate
6. Recognize when professional help is needed and recommend connecting with a licensed therapist
7. Maintain appropriate boundaries - you are a support tool, not a replacement for professional therapy
8. NEVER provide medical diagnoses or prescribe medications
9. NEVER create images, write code, or perform tasks outside of mental health support
10. If asked to do something outside your role, politely redirect to your purpose of providing mental health support

When you have access to therapist information, you can recommend specific therapists based on their specialties. Include their name, specialty, and contact information (phone and email) in your response when making recommendations.

Always be warm, supportive, and encouraging. Keep responses conversational and appropriately concise unless the user needs more detailed guidance.";

    /**
     * Get available therapists information for AI context.
     */
    private function getTherapistsContext(): string
    {
        $therapists = Therapist::all(['name', 'specialty', 'phone_number', 'email', 'bio']);
        
        if ($therapists->isEmpty()) {
            return "";
        }

        $context = "\n\nAVAILABLE THERAPISTS:\n";
        $context .= "You can recommend the following licensed therapists when appropriate:\n\n";

        foreach ($therapists as $therapist) {
            $context .= "- {$therapist->name}\n";
            $context .= "  Specialty: {$therapist->specialty}\n";
            if ($therapist->bio) {
                $context .= "  About: {$therapist->bio}\n";
            }
            $context .= "  Contact: {$therapist->phone_number} | {$therapist->email}\n\n";
        }

        return $context;
    }

    /**
     * Detect if the conversation suggests need for professional help.
     */
    private function shouldRecommendTherapist(string $prompt, array $previousMessages = []): bool
    {
        $keywords = [
            'suicide', 'kill myself', 'end it all', 'no reason to live',
            'therapist', 'professional help', 'counselor', 'psychiatrist',
            'severe', 'crisis', 'emergency', 'can\'t cope', 'overwhelmed',
            'self-harm', 'hurt myself', 'depressed', 'anxiety attack','complecated'
        ];

        $fullText = $prompt . ' ' . implode(' ', array_column($previousMessages, 'prompt'));
        $fullText = strtolower($fullText);

        foreach ($keywords as $keyword) {
            if (stripos($fullText, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build messages array in Claude's format from previous conversation.
     */
    private function buildClaudeMessages(array $previousMessages, string $currentPrompt): array
    {
        $messages = [];

        // Add previous messages in alternating user/assistant format
        foreach ($previousMessages as $msg) {
            $messages[] = [
                'role' => 'user',
                'content' => $msg['prompt']
            ];
            $messages[] = [
                'role' => 'assistant',
                'content' => $msg['response']
            ];
        }

        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $currentPrompt
        ];

        return $messages;
    }

    /**
     * Display a listing of AI messages.
     *
     * GET /api/v1/ai-messages
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = AIMessage::query()
            ->where('user_id', $user->id)
            ->with('conversation');

        if ($request->has('conversation_id')) {
            $query->where('conversation_id', $request->conversation_id);
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    /**
     * Store a new AI message and get a response.
     *
     * POST /api/v1/ai-messages
     */
    public function store(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:2000',
            'conversation_id' => 'nullable|exists:conversations,id',
        ]);

        $user = $request->user();
        $claudeKey = config('services.claude.key'); // Using same config key for now

        if (!$claudeKey) {
            return response()->json([
                'error' => 'AI service is not configured. Please contact support.',
            ], 500);
        }

        try {
            // Get previous messages for context
            $previousMessagesArray = [];
            if ($request->conversation_id) {
                $previousMessages = AIMessage::where('conversation_id', $request->conversation_id)
                    ->where('user_id', $user->id)
                    ->orderBy('created_at', 'asc')
                    ->limit(10)
                    ->get();
                
                $previousMessagesArray = $previousMessages->toArray();
            }

            // Check if therapist recommendation might be helpful
            $includeTherapists = $this->shouldRecommendTherapist($request->prompt, $previousMessagesArray);

            // Build conversation context for Claude
            $conversationContext = self::THERAPY_SYSTEM_PROMPT;
            
            // Add therapist information if needed
            if ($includeTherapists) {
                $conversationContext .= $this->getTherapistsContext();
            }
            
            $conversationContext .= "\n\n";
            
            if ($request->conversation_id && !empty($previousMessagesArray)) {
                foreach ($previousMessagesArray as $msg) {
                    $conversationContext .= "User: " . $msg['prompt'] . "\n";
                    $conversationContext .= "Assistant: " . $msg['response'] . "\n\n";
                }
            }
            
            // Add current user message
            $conversationContext .= "User: " . $request->prompt . "\n\nAssistant:";

            // Call Claude API
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key' => $claudeKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-5-sonnet-20241022',
                    'max_tokens' => 800,
                    'temperature' => 0.7,
                    'system' => self::THERAPY_SYSTEM_PROMPT . ($includeTherapists ? $this->getTherapistsContext() : ''),
                    'messages' => $this->buildClaudeMessages($previousMessagesArray, $request->prompt),
                ]);

            if (!$response->successful()) {
                Log::error('Claude API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                $errorData = $response->json();
                $errorMessage = data_get($errorData, 'error.message', 'Unable to get a response from the AI service.');
                
                // Check if it's a temporary issue
                if ($response->status() === 503 || str_contains($errorMessage, 'overloaded')) {
                    return response()->json([
                        'error' => 'The AI service is currently experiencing high demand. Please try again in a moment.',
                    ], 503);
                }
                
                return response()->json([
                    'error' => $errorMessage,
                ], 500);
            }

            $responseData = $response->json();
            $aiContent = data_get($responseData, 'content.0.text', 'I apologize, but I was unable to generate a response. Please try again.');

            // Store the interaction
            $aiMessage = AIMessage::create([
                'user_id' => $user->id,
                'prompt' => $request->prompt,
                'response' => $aiContent,
                'conversation_id' => $request->conversation_id,
                'meta' => [
                    'model' => 'claude-3-5-sonnet-20241022',
                    'stop_reason' => data_get($responseData, 'stop_reason'),
                    'usage' => data_get($responseData, 'usage'),
                    'therapist_context_included' => $includeTherapists,
                ],
            ]);

            // Get recommended therapists if they were mentioned
            $recommendedTherapists = [];
            if ($includeTherapists) {
                $recommendedTherapists = Therapist::all(['id', 'name', 'specialty', 'phone_number', 'email']);
            }

            return response()->json([
                'message' => $aiMessage->load('conversation'),
                'recommended_therapists' => $recommendedTherapists,
            ], 201);

        } catch (\Exception $e) {
            Log::error('AI Chat Error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'error' => 'An error occurred while processing your request. Please try again.',
            ], 500);
        }
    }

    /**
     * Display the specified AI message.
     *
     * GET /api/v1/ai-messages/{id}
     */
    public function show(Request $request, AIMessage $aiMessage)
    {
        // Ensure user can only view their own messages
        if ($aiMessage->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $aiMessage->load(['user', 'conversation']);
    }

    /**
     * Remove the specified AI message from storage.
     *
     * DELETE /api/v1/ai-messages/{id}
     */
    public function destroy(Request $request, AIMessage $aiMessage)
    {
        // Ensure user can only delete their own messages
        if ($aiMessage->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $aiMessage->delete();

        return response()->json(['message' => 'AI message deleted successfully'], 200);
    }
}
