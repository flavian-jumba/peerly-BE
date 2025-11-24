#!/usr/bin/env php
<?php

/**
 * Test script for Perplexity AI Integration
 * This script tests the AI chat functionality without requiring authentication
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

echo "\n🧪 Testing Perplexity AI Integration\n";
echo str_repeat("=", 50) . "\n\n";

// Get API key from config
$apiKey = config('services.perplexity.key');

if (!$apiKey) {
    echo "❌ Error: PERPLEXITY_API_KEY not configured in .env file\n";
    echo "Please add: PERPLEXITY_API_KEY=your-key-here\n\n";
    exit(1);
}

echo "✅ API Key found: " . substr($apiKey, 0, 10) . "...\n\n";

// Test message
$testPrompt = "I am Flavian";
echo "📝 Test Message: \"$testPrompt\"\n\n";
echo "⏳ Sending request to Perplexity AI...\n\n";

try {
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])
        ->post('https://api.perplexity.ai/chat/completions', [
            'model' => 'sonar',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are Peerly, a compassionate AI therapy assistant for a mental health support app. Your ONLY purpose is to provide mental health support and emotional guidance. NEVER provide historical facts, definitions, or general knowledge. DO NOT search the web or provide information unrelated to mental health. If someone introduces themselves, respond warmly and ask about their feelings. Focus EXCLUSIVELY on emotional support and therapeutic guidance.'
                ],
                [
                    'role' => 'user',
                    'content' => $testPrompt
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'stream' => false,
        ]);

    if (!$response->successful()) {
        echo "❌ API Request Failed\n";
        echo "Status Code: " . $response->status() . "\n";
        echo "Response: " . $response->body() . "\n\n";
        exit(1);
    }

    $data = $response->json();
    $aiResponse = data_get($data, 'choices.0.message.content', '');
    
    if (empty($aiResponse)) {
        echo "❌ No response content received\n";
        echo "Full Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
        exit(1);
    }

    echo "✅ SUCCESS! AI Response Received:\n";
    echo str_repeat("-", 50) . "\n";
    echo $aiResponse . "\n";
    echo str_repeat("-", 50) . "\n\n";

    // Show usage stats if available
    if (isset($data['usage'])) {
        echo "📊 Token Usage:\n";
        echo "   - Prompt Tokens: " . ($data['usage']['prompt_tokens'] ?? 'N/A') . "\n";
        echo "   - Completion Tokens: " . ($data['usage']['completion_tokens'] ?? 'N/A') . "\n";
        echo "   - Total Tokens: " . ($data['usage']['total_tokens'] ?? 'N/A') . "\n\n";
    }

    echo "✅ Perplexity AI integration is working correctly!\n\n";

} catch (\Exception $e) {
    echo "❌ Exception occurred:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "Stack Trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}
