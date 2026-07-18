<?php

return [
    'ai' => [
        'mode' => env('NO_EXCUSE_AI_MODE', 'demo'),
        'providers' => [
            'openai' => 'OpenAI / ChatGPT',
            'anthropic' => 'Anthropic / Claude',
            'gemini' => 'Google Gemini',
            'mistral' => 'Mistral AI',
            'groq' => 'Groq',
            'deepseek' => 'DeepSeek',
            'openrouter' => 'OpenRouter',
            'ollama' => 'Ollama local',
            'openai-compatible' => 'API compatible OpenAI',
        ],
        'defaults' => [
            'openai' => ['screening' => env('OPENAI_SCREENING_MODEL', 'gpt-5-mini'), 'scoring' => env('OPENAI_SCORING_MODEL', 'gpt-5')],
            'anthropic' => ['screening' => env('ANTHROPIC_SCREENING_MODEL', 'claude-haiku-4-5'), 'scoring' => env('ANTHROPIC_SCORING_MODEL', 'claude-sonnet-4-5')],
            'gemini' => ['screening' => env('GEMINI_SCREENING_MODEL', 'gemini-2.5-flash-lite'), 'scoring' => env('GEMINI_SCORING_MODEL', 'gemini-2.5-pro')],
            'mistral' => ['screening' => env('MISTRAL_SCREENING_MODEL', 'mistral-small-latest'), 'scoring' => env('MISTRAL_SCORING_MODEL', 'mistral-large-latest')],
            'groq' => ['screening' => env('GROQ_SCREENING_MODEL', 'openai/gpt-oss-20b'), 'scoring' => env('GROQ_SCORING_MODEL', 'openai/gpt-oss-120b')],
            'deepseek' => ['screening' => env('DEEPSEEK_SCREENING_MODEL', 'deepseek-chat'), 'scoring' => env('DEEPSEEK_SCORING_MODEL', 'deepseek-reasoner')],
            'openrouter' => ['screening' => env('OPENROUTER_SCREENING_MODEL', 'openai/gpt-5-mini'), 'scoring' => env('OPENROUTER_SCORING_MODEL', 'anthropic/claude-sonnet-4.5')],
            'ollama' => ['screening' => env('OLLAMA_SCREENING_MODEL', 'qwen3:4b'), 'scoring' => env('OLLAMA_SCORING_MODEL', 'qwen3:14b')],
            'openai-compatible' => ['screening' => env('COMPATIBLE_SCREENING_MODEL', 'qwen3:4b'), 'scoring' => env('COMPATIBLE_SCORING_MODEL', 'qwen3:14b')],
        ],
    ],
    'scope_threshold' => (float) env('NO_EXCUSE_SCOPE_THRESHOLD', 25),
];
