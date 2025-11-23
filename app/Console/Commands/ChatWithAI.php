<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\AIMessage;
use App\Models\Therapist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ChatWithAI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:ai {--user-id=1 : The user ID to chat as}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactive chat with the AI therapy assistant';

    /**
     * The system prompt for the AI.
     */
    private const THERAPY_SYSTEM_PROMPT = "You are a compassionate and empathetic AI therapy assistant for a mental health support app called Peerly. Your role is to:

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
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            $this->warn('Available users:');
            User::all(['id', 'name', 'email'])->each(function ($u) {
                $this->line("  ID: {$u->id} - {$u->name} ({$u->email})");
            });
            return 1;
        }

        $geminiKey = config('services.gemini.key');
        if (!$geminiKey) {
            $this->error('Gemini API key not configured!');
            return 1;
        }

        $this->info("╔═══════════════════════════════════════════════════════════╗");
        $this->info("║        AI Therapy Chat - Testing Interface               ║");
        $this->info("╚═══════════════════════════════════════════════════════════╝");
        $this->newLine();
        $this->line("Chatting as: <fg=cyan>{$user->name}</> ({$user->email})");
        $this->newLine();
        $this->comment("Type your message and press Enter to chat.");
        $this->comment("Type 'exit' or 'quit' to end the conversation.");
        $this->comment("Type 'clear' to start a new conversation.");
        $this->comment("Type 'therapists' to see available therapists.");
        $this->newLine();

        $conversationHistory = [];

        while (true) {
            $prompt = $this->ask('<fg=green>You</>');

            if (empty($prompt)) {
                continue;
            }

            $prompt = trim($prompt);

            if (in_array(strtolower($prompt), ['exit', 'quit'])) {
                $this->info('Goodbye! Take care of yourself. 💚');
                break;
            }

            if (strtolower($prompt) === 'clear') {
                $conversationHistory = [];
                $this->newLine();
                $this->info('✓ Conversation cleared. Starting fresh!');
                $this->newLine();
                continue;
            }

            if (strtolower($prompt) === 'therapists') {
                $this->showTherapists();
                continue;
            }

            // Check if therapist context should be included
            $includeTherapists = $this->shouldRecommendTherapist($prompt, $conversationHistory);

            // Build context
            $context = self::THERAPY_SYSTEM_PROMPT;

            if ($includeTherapists) {
                $context .= $this->getTherapistsContext();
                $this->line("<fg=yellow>ℹ Including therapist information in context...</>");
            }

            $context .= "\n\n";

            foreach ($conversationHistory as $msg) {
                $context .= "User: {$msg['user']}\n";
                $context .= "Assistant: {$msg['assistant']}\n\n";
            }

            $context .= "User: {$prompt}\n\nAssistant:";

            // Call Gemini API
            $this->line("<fg=gray>AI is thinking...</>");
            
            try {
                $response = Http::timeout(30)
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $geminiKey, [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $context]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 800,
                        ],
                    ]);

                if (!$response->successful()) {
                    $this->error('API Error: ' . $response->status());
                    $this->line($response->body());
                    continue;
                }

                $responseData = $response->json();
                $aiResponse = data_get($responseData, 'candidates.0.content.parts.0.text', 'No response generated.');

                // Add to conversation history
                $conversationHistory[] = [
                    'user' => $prompt,
                    'assistant' => $aiResponse,
                ];

                // Keep only last 10 exchanges
                if (count($conversationHistory) > 10) {
                    array_shift($conversationHistory);
                }

                $this->newLine();
                $this->line("<fg=cyan>AI Assistant:</>");
                $this->line($aiResponse);
                $this->newLine();

            } catch (\Exception $e) {
                $this->error('Error: ' . $e->getMessage());
                continue;
            }
        }

        return 0;
    }

    /**
     * Get therapists context.
     */
    private function getTherapistsContext(): string
    {
        $therapists = Therapist::all(['name', 'specialty', 'phone_number', 'email', 'bio']);
        
        if ($therapists->isEmpty()) {
            return "\n\n(Note: No therapists are currently available in the system)";
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
     * Detect if therapist recommendation might be helpful.
     */
    private function shouldRecommendTherapist(string $prompt, array $history): bool
    {
        $keywords = [
            'suicide', 'kill myself', 'end it all', 'no reason to live',
            'therapist', 'professional help', 'counselor', 'psychiatrist',
            'severe', 'crisis', 'emergency', 'can\'t cope', 'overwhelmed',
            'self-harm', 'hurt myself', 'depressed', 'anxiety attack',
            'recommend', 'need help'
        ];

        $fullText = $prompt . ' ' . implode(' ', array_column($history, 'user'));
        $fullText = strtolower($fullText);

        foreach ($keywords as $keyword) {
            if (stripos($fullText, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Show available therapists.
     */
    private function showTherapists(): void
    {
        $therapists = Therapist::all();

        if ($therapists->isEmpty()) {
            $this->warn('No therapists are currently registered in the system.');
            return;
        }

        $this->newLine();
        $this->info('Available Therapists:');
        $this->newLine();

        foreach ($therapists as $therapist) {
            $this->line("<fg=cyan>{$therapist->name}</>");
            $this->line("  Specialty: {$therapist->specialty}");
            if ($therapist->bio) {
                $this->line("  Bio: {$therapist->bio}");
            }
            $this->line("  Phone: {$therapist->phone_number}");
            $this->line("  Email: {$therapist->email}");
            $this->newLine();
        }
    }
}
