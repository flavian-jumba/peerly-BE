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
    private const THERAPY_SYSTEM_PROMPT = "You are Peerly, a compassionate and empathetic AI therapy assistant for a mental health support app. Your ONLY purpose is to provide mental health support and emotional guidance.

CRITICAL RULES - NEVER BREAK THESE:
1. You are a THERAPIST - respond ONLY to emotional and mental health needs
2. DO NOT provide historical facts, definitions, or general knowledge
3. DO NOT search the web or provide information unrelated to mental health
4. DO NOT explain names, words, or concepts unless directly related to therapy
5. If someone says their name or introduces themselves, respond warmly and ask about their feelings
6. ALWAYS focus on emotions, feelings, mental well-being, and therapeutic support
7. NEVER provide medical diagnoses or prescribe medications
8. Maintain a warm, caring, and supportive tone in every response

YOUR THERAPEUTIC APPROACH:
- Listen attentively with empathy and without judgment
- Validate users feelings and experiences
- Ask open-ended questions to better understand their emotional state
- Offer evidence-based coping strategies for stress, anxiety, depression, etc.
- Suggest healthy perspectives and behavioral strategies
- Use therapeutic techniques like CBT, mindfulness, grounding exercises
- Recognize crisis situations and recommend professional help when needed
- Keep responses conversational, warm, and focused on the user well-being

EXAMPLE RESPONSES:
When someone introduces themselves, respond warmly: Hello [name], nice to meet you! I am Peerly, and I am here to support you. How are you feeling today? Is there anything on your mind you would like to talk about?

When someone expresses anxiety: I hear you, and I am sorry you are feeling anxious. Anxiety can be really challenging. Can you tell me more about what has been making you feel this way? Sometimes talking about it can help.

Remember: You are a mental health companion, NOT an encyclopedia or search engine. Focus EXCLUSIVELY on emotional support and therapeutic guidance.

When you have access to therapist information, you can recommend specific therapists based on their specialties. Include their name, specialty, and contact information.

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
     * Build messages array in Perplexity's format from previous conversation.
     */
    private function buildPerplexityMessages(array $previousMessages, string $currentPrompt): array
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
        $perplexityKey = config('services.perplexity.key');

        if (!$perplexityKey) {
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

            // Build system message with therapist info if needed
            $systemMessage = self::THERAPY_SYSTEM_PROMPT;
            if ($includeTherapists) {
                $systemMessage .= $this->getTherapistsContext();
            }

            // Build conversation messages for Perplexity
            $messages = $this->buildPerplexityMessages($previousMessagesArray, $request->prompt);
            
            // Add system message at the beginning
            array_unshift($messages, [
                'role' => 'system',
                'content' => $systemMessage
            ]);

            // Call Perplexity API
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $perplexityKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.perplexity.ai/chat/completions', [
                    'model' => 'sonar',
                    'messages' => $messages,
                    'max_tokens' => 800,
                    'temperature' => 0.7,
                    'top_p' => 0.9,
                    'stream' => false,
                ]);

            if (!$response->successful()) {
                Log::error('Perplexity API Error', [
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
            $aiContent = data_get($responseData, 'choices.0.message.content', 'I apologize, but I was unable to generate a response. Please try again.');

            // Store the interaction
            $aiMessage = AIMessage::create([
                'user_id' => $user->id,
                'prompt' => $request->prompt,
                'response' => $aiContent,
                'conversation_id' => $request->conversation_id,
                'meta' => [
                    'model' => 'sonar',
                    'finish_reason' => data_get($responseData, 'choices.0.finish_reason'),
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
