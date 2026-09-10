<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => env('AI_TEXT_PROVIDER', 'openai'),
    'default_for_images' => env('AI_IMAGE_PROVIDER', 'openai'),
    'default_for_audio' => env('AI_AUDIO_PROVIDER', 'openai'),
    'default_for_transcription' => env('AI_TRANSCRIPTION_PROVIDER', 'openai'),
    'default_for_embeddings' => env('AI_EMBEDDINGS_PROVIDER', 'openai'),
    'default_for_reranking' => env('AI_RERANKING_PROVIDER', 'cohere'),

    /*
    |--------------------------------------------------------------------------
    | Text Generation Resilience & Cost
    |--------------------------------------------------------------------------
    |
    | `text.failover` is an optional ordered list of provider names the SDK
    | falls back through when the primary text provider throws a failoverable
    | error (timeout, 5xx, rate limit). Leave it empty (the default) to run
    | single-provider exactly as before; set e.g. AI_TEXT_FAILOVER="openai,gemini"
    | to enable cross-provider failover on the critical chat/generation agents.
    | Every listed provider must be configured with credentials below.
    |
    | `text.chat.max_conversation_messages` caps how many stored messages the
    | interactive workspace chat agent replays each turn (the SDK default is
    | 100). A tighter window cuts tokens/cost/latency on long conversations at
    | the price of shorter memory; raise it if the assistant forgets context.
    |
    */

    'text' => [
        'failover' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_TEXT_FAILOVER', '')),
        ))),

        'chat' => [
            'max_conversation_messages' => (int) env('AI_CHAT_MAX_MESSAGES', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Generation Resilience
    |--------------------------------------------------------------------------
    |
    | The image endpoint (e.g. BytePlus Seedream via the OpenAI-compatible
    | driver) is the flakiest hop in the AI post pipeline: a provider timeout,
    | a 5xx, or a body the SDK cannot parse all surface as a thrown error. To
    | avoid failing a whole generation on a single transient hiccup,
    | AiImageClient retries the call up to `max_attempts` times with a linear
    | backoff of `retry_delay_ms` between attempts. Set max_attempts to 1 to
    | disable retrying.
    |
    */

    'image' => [
        'max_attempts' => (int) env('AI_IMAGE_MAX_ATTEMPTS', 3),
        'retry_delay_ms' => (int) env('AI_IMAGE_RETRY_DELAY_MS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'models' => [
                'text' => ['default' => env('ANTHROPIC_TEXT_MODEL')],
            ],
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2025-04-01-preview'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
            'image_deployment' => env('AZURE_OPENAI_IMAGE_DEPLOYMENT', 'gpt-image-1'),
            'store' => env('AZURE_OPENAI_STORE', true),
        ],

        'bedrock' => [
            'driver' => 'bedrock',
            'region' => env('AWS_BEDROCK_REGION', 'us-east-1'),
            'key' => env('AWS_BEARER_TOKEN_BEDROCK'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'session_token' => env('AWS_SESSION_TOKEN'),
            'use_default_credential_provider' => env('AWS_USE_DEFAULT_CREDENTIALS', true),
            'assume_role' => [
                'arn' => env('AWS_BEDROCK_ASSUME_ROLE_ARN'),
                'session_name' => env('AWS_BEDROCK_ASSUME_ROLE_SESSION_NAME'),
                'duration_seconds' => env('AWS_BEDROCK_ASSUME_ROLE_DURATION_SECONDS'),
                'external_id' => env('AWS_BEDROCK_ASSUME_ROLE_EXTERNAL_ID'),
            ],
            'models' => [
                'text' => ['default' => env('AWS_BEDROCK_TEXT_MODEL')],
                'image' => ['default' => env('AWS_BEDROCK_IMAGE_MODEL')],
                'embeddings' => ['default' => env('AWS_BEDROCK_EMBEDDINGS_MODEL')],
            ],
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
            'models' => [
                'embeddings' => ['default' => env('COHERE_EMBEDDINGS_MODEL')],
                'reranking' => ['default' => env('COHERE_RERANKING_MODEL')],
            ],
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
            'models' => [
                'text' => ['default' => env('DEEPSEEK_TEXT_MODEL')],
            ],
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
            'models' => [
                'audio' => ['default' => env('ELEVENLABS_AUDIO_MODEL')],
                'transcription' => ['default' => env('ELEVENLABS_TRANSCRIPTION_MODEL')],
            ],
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
            'models' => [
                'text' => ['default' => env('GEMINI_TEXT_MODEL')],
                'image' => ['default' => env('GEMINI_IMAGE_MODEL')],
                'audio' => ['default' => env('GEMINI_AUDIO_MODEL')],
                'transcription' => ['default' => env('GEMINI_TRANSCRIPTION_MODEL')],
                'embeddings' => ['default' => env('GEMINI_EMBEDDINGS_MODEL')],
            ],
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
            'models' => [
                'text' => ['default' => env('GROQ_TEXT_MODEL')],
            ],
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
            'models' => [
                'embeddings' => ['default' => env('JINA_EMBEDDINGS_MODEL')],
                'reranking' => ['default' => env('JINA_RERANKING_MODEL')],
            ],
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
            'models' => [
                'text' => ['default' => env('MISTRAL_TEXT_MODEL')],
                'transcription' => ['default' => env('MISTRAL_TRANSCRIPTION_MODEL')],
                'embeddings' => ['default' => env('MISTRAL_EMBEDDINGS_MODEL')],
            ],
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'models' => [
                'text' => ['default' => env('OLLAMA_TEXT_MODEL')],
                'embeddings' => ['default' => env('OLLAMA_EMBEDDINGS_MODEL')],
            ],
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'store' => env('OPENAI_STORE', true),
            'models' => [
                'text' => ['default' => env('OPENAI_TEXT_MODEL')],
                'image' => ['default' => env('OPENAI_IMAGE_MODEL')],
                'audio' => ['default' => env('OPENAI_AUDIO_MODEL')],
                'transcription' => ['default' => env('OPENAI_TRANSCRIPTION_MODEL')],
                'embeddings' => ['default' => env('OPENAI_EMBEDDINGS_MODEL')],
            ],
        ],

        'openai-compatible' => [
            'driver' => 'openai-compatible',
            'url' => env('OPENAI_COMPATIBLE_URL'),
            'key' => env('OPENAI_COMPATIBLE_API_KEY'),
            'models' => [
                'text' => ['default' => env('OPENAI_COMPATIBLE_TEXT_MODEL')],
                'embeddings' => ['default' => env('OPENAI_COMPATIBLE_EMBEDDINGS_MODEL')],
            ],
        ],

        'seedream' => [
            // BytePlus Ark Seedream. Not a laravel/ai driver: its unified
            // generate-edit endpoint (/images/generations with an `image` URL
            // array for image-to-image) is not OpenAI-compatible, so it is
            // driven by App\Services\Ai\SeedreamImageClient over plain HTTP.
            'driver' => 'seedream',
            'url' => env('SEEDREAM_URL', 'https://ark.ap-southeast.bytepluses.com/api/v3'),
            'key' => env('SEEDREAM_API_KEY'),
            'watermark' => env('SEEDREAM_WATERMARK', false),
            'models' => [
                'image' => ['default' => env('SEEDREAM_IMAGE_MODEL', 'seedream-4-5-251128')],
            ],
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
            'models' => [
                'text' => ['default' => env('OPENROUTER_TEXT_MODEL')],
                'image' => ['default' => env('OPENROUTER_IMAGE_MODEL')],
                'audio' => ['default' => env('OPENROUTER_AUDIO_MODEL')],
                'transcription' => ['default' => env('OPENROUTER_TRANSCRIPTION_MODEL')],
                'embeddings' => ['default' => env('OPENROUTER_EMBEDDINGS_MODEL')],
            ],
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
            'models' => [
                'embeddings' => ['default' => env('VOYAGEAI_EMBEDDINGS_MODEL')],
                'reranking' => ['default' => env('VOYAGEAI_RERANKING_MODEL')],
            ],
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
            'models' => [
                'text' => ['default' => env('XAI_TEXT_MODEL')],
                'image' => ['default' => env('XAI_IMAGE_MODEL')],
            ],
        ],
    ],
];
