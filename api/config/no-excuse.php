<?php

return [
    'public_demo' => [
        'enabled' => (bool) env('NO_EXCUSE_PUBLIC_DEMO', false),
        'lifetime_hours' => (int) env('NO_EXCUSE_DEMO_LIFETIME_HOURS', 4),
        'max_sessions' => (int) env('NO_EXCUSE_DEMO_MAX_SESSIONS', 100),
        'processing_delay_seconds' => (int) env('NO_EXCUSE_DEMO_PROCESSING_DELAY_SECONDS', 2),
        'screening_workers' => (int) env('NO_EXCUSE_DEMO_SCREENING_WORKERS', 2),
        'scoring_workers' => (int) env('NO_EXCUSE_DEMO_SCORING_WORKERS', 1),
    ],
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
        // Only these booleans may be exposed to authenticated recruiters. Secret
        // values remain in the server environment and never enter an API payload.
        'credentials' => [
            'openai' => filled(env('OPENAI_API_KEY')),
            'anthropic' => filled(env('ANTHROPIC_API_KEY')),
            'gemini' => filled(env('GEMINI_API_KEY')),
            'mistral' => filled(env('MISTRAL_API_KEY')),
            'groq' => filled(env('GROQ_API_KEY')),
            'deepseek' => filled(env('DEEPSEEK_API_KEY')),
            'openrouter' => filled(env('OPENROUTER_API_KEY')),
            'ollama' => filled(env('OLLAMA_URL')),
            'openai-compatible' => filled(env('OPENAI_COMPATIBLE_URL')) && filled(env('OPENAI_COMPATIBLE_API_KEY')),
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
    'prompts' => [
        'screening' => 'Évalue uniquement si le CV correspond au périmètre professionnel explicite de l’annonce. Ignore le nom, l’âge, le genre, l’origine, la photographie, l’adresse et toute autre donnée sensible. Un profil doit être conservé dès lors que ses compétences transférables rendent une analyse approfondie raisonnable.',
        'scoring' => 'Compare le CV aux critères professionnels annoncés. Justifie chaque score par des éléments observables du CV, distingue les compétences démontrées des simples mentions et ne déduis jamais une caractéristique personnelle. Le score aide le RH à prioriser la lecture et ne constitue pas une décision automatique.',
    ],
];
