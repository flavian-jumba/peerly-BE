<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestOpenAI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:openai';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test OpenAI API connection and response';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Gemini API connection...');
        $this->newLine();

        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            $this->error('❌ Gemini API key not found in config!');
            $this->warn('Make sure OPENAI_API_KEY is set in your .env file');
            return 1;
        }

        $this->info('✓ API key found');
        $this->newLine();

        try {
            $this->info('Sending test request to Gemini...');
            
            $response = Http::timeout(30)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => 'You are a helpful therapy assistant. Say "Hello, I am working!" if you can read this.'
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 50,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = data_get($data, 'candidates.0.content.parts.0.text', 'No response');
                
                $this->newLine();
                $this->info('✓ Success! Gemini API is working correctly.');
                $this->newLine();
                $this->line('<fg=cyan>Response:</>');
                $this->line($content);
                $this->newLine();
                $this->line('<fg=yellow>Model:</> gemini-2.5-flash');
                $this->line('<fg=yellow>Finish Reason:</> ' . data_get($data, 'candidates.0.finishReason', 'unknown'));
                $this->newLine();
                
                return 0;
            } else {
                $this->error('❌ API request failed!');
                $this->newLine();
                $this->line('<fg=red>Status:</> ' . $response->status());
                $this->line('<fg=red>Response:</> ' . $response->body());
                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ Error occurred:');
            $this->error($e->getMessage());
            return 1;
        }
    }
}
