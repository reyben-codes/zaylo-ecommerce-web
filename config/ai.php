<?php

/*
| The AI models behind Zay, the help bot (App\Services\ZayAssistant). They are tried in this order, each until it runs
| out of quota or fails; one without an API key is skipped. All of them use the OpenAI chat completions format. "options"
| are extra request fields for that provider. With no keys at all, Zay answers from config/faq.php alone.
*/

return [
    // The longest one question may keep a person waiting across all providers, and one provider's share of that.
    'budget_seconds' => 15,
    'timeout_seconds' => 8,

    // Room for the reply and any hidden reasoning; the instructions ask for a short answer.
    'max_tokens' => 600,

    'providers' => [
        [
            'name' => 'groq',
            'url' => 'https://api.groq.com/openai/v1/chat/completions',
            'key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
            'options' => ['reasoning_effort' => 'low'],
        ],
        [
            'name' => 'gemini',
            'url' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
        ],
        [
            'name' => 'openrouter',
            'url' => 'https://openrouter.ai/api/v1/chat/completions',
            'key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'openrouter/free'),
        ],
    ],
];
